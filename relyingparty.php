<?php

require_once 'vendor/lbuchs/webauthn/src/WebAuthn.php';

try {
    session_start();

    // read get argument and post body
    $fn = filter_input(INPUT_GET, 'fn');

    $requireResidentKey = 0;

    $userId = '1ccabd78a9b1472ba3c3d1fc1e93fd95'; // Generated Random UUID for user
    $userName = 'admin';
    $userDisplayName = 'Pi-hole Admin';

    $rpId = $_SERVER['HTTP_HOST'];
    $rpName = 'Pi-hole Dashboard';

    $formats[] = 'none';    // We dont want to receive attestation information

    // userVerification: discouraged, RP does not want user verification (seemless experience)
    //                   preferred, RP prefers user verification for the operation if possible
    //                   required, RP requires user verification for the operation and will fail the operation
    $userVerification = 'discouraged';

    // cross-platform: true, if type internal is not allowed
    //                 false, if only internal is allowed
    //                 null, both internal and cross-platform is allowed
    $crossPlatformAttachment = null;

    // new Instance of the server library.
    $WebAuthn = new lbuchs\WebAuthn\WebAuthn($rpName, $rpId, $formats);

    $WebAuthnUserDatabase = '../webauthn_users.data'; // Local File for User Store

    $post = trim(file_get_contents('php://input'));
    if ($post) {
        $post = json_decode($post);
    }

    // ------------------------------------------------------------------------
    // WebAuthn Client Registration - Request for create arguments
    // ------------------------------------------------------------------------

    if ($fn === 'getCreateArgs') {

        // User should be authenticated to perform this.
        if ($_SESSION['auth'] != true) {
            throw new \Exception('Only authenticated users can perform action');
        }

        $createArgs = $WebAuthn->getCreateArgs(\hex2bin($userId), $userName, $userDisplayName, 20, $requireResidentKey, $userVerification, $crossPlatformAttachment);

        header('Content-Type: application/json');
        print(json_encode($createArgs));

        // save challange to session. you have to deliver it to processGet later.
        $_SESSION['challenge'] = $WebAuthn->getChallenge();

    // ------------------------------------------------------------------------
    //  WebAuthn Client Registration - Request for process create
    // ------------------------------------------------------------------------

    } else if ($fn === 'processCreate') {

        // User should be authenticated to perform this.
        if ($_SESSION['auth'] != true) {
            throw new \Exception('Only authenticated users can perform action');
        }

        $clientDataJSON = base64_decode($post->clientDataJSON);
        $attestationObject = base64_decode($post->attestationObject);
        $challenge = $_SESSION['challenge'];

        // processCreate returns data to be stored for future logins.
        $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, $userVerification === 'required', true, false);

        // add user infos
        $data->userId = $userId;
        $data->userName = $userName;
        $data->userDisplayName = $userDisplayName;

        // store userdata in database
        $webauthnUserData = unserialize(file_get_contents($WebAuthnUserDatabase));
        $webauthnUserData["registrations"][] = $data;
        file_put_contents($WebAuthnUserDatabase, serialize($webauthnUserData));

        $msg = 'registration success.';

        $return = new stdClass();
        $return->success = true;
        $return->msg = $msg;

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------------------------------------------
    // WebAuthn Client Authentication - request for get arguments
    // ------------------------------------------------------------------------

    } else if ($fn === 'getGetArgs') {

        $ids = array();

        if ($requireResidentKey) {
            if (!is_array($_SESSION['registrations']) || count($_SESSION['registrations']) === 0) {
                throw new Exception('we do not have any registrations in session to check the registration');
            }

        } else {
            // query userdata database for known userIds
            $webauthnUserData = unserialize(file_get_contents($WebAuthnUserDatabase));
            if (is_array($webauthnUserData["registrations"])) {
                foreach ($webauthnUserData["registrations"] as $reg) {
                    if ($reg->userId === $userId) {
                        $ids[] = $reg->credentialId;
                    }
                }
            }

            if (count($ids) === 0) {
                throw new Exception('no registrations in session for userId ' . $userId);
            }
        }

        $getArgs = $WebAuthn->getGetArgs($ids, 20, $typeUsb, $typeNfc, $typeBle, $typeInt, $userVerification);

        header('Content-Type: application/json');
        print(json_encode($getArgs));

        // save challange to session. you have to deliver it to processGet later.
        $_SESSION['challenge'] = $WebAuthn->getChallenge();

    // ------------------------------------------------------------------------
    // WebAuthn Client Authentication - proccess get
    // ------------------------------------------------------------------------

    } else if ($fn === 'processGet') {

        $clientDataJSON = base64_decode($post->clientDataJSON);
        $authenticatorData = base64_decode($post->authenticatorData);
        $signature = base64_decode($post->signature);
        $userHandle = base64_decode($post->userHandle);
        $id = base64_decode($post->id);
        $challenge = $_SESSION['challenge'];
        $credentialPublicKey = null;

        // query correspondending public key of the credential id for validation
        $webauthnUserData = unserialize(file_get_contents($WebAuthnUserDatabase));
        if (is_array($webauthnUserData['registrations'])) {
            foreach ($webauthnUserData['registrations'] as $reg) {
                if ($reg->credentialId === $id) {
                    $credentialPublicKey = $reg->credentialPublicKey;
                    break;
                }
            }
        }

        if ($credentialPublicKey === null) {
            throw new Exception('Public Key for credential ID not found!');
        }

        // if we have resident key, we have to verify that the userHandle is the provided userId at registration
        if ($requireResidentKey && $userHandle !== hex2bin($reg->userId)) {
            throw new \Exception('userId doesnt match (is ' . bin2hex($userHandle) . ' but expect ' . $reg->userId . ')');
        }

        // process the get request. throws WebAuthnException if it fails
        $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $credentialPublicKey, $challenge, null, $userVerification === 'required');

        $return = new stdClass();
        $return->success = true;

        $_SESSION['auth'] = true; // OK - authenticate the session

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // clear registrations
    // ------------------------------------

    } else if ($fn === 'clearRegistrations') {

        // User should be authenticated to perform this.
        if ($_SESSION['auth'] != true) {
            throw new \Exception('Only authenticated users can perform action');
        }

        file_put_contents($WebAuthnUserDatabase, '');

        $return = new stdClass();
        $return->success = true;
        $return->msg = 'all registrations deleted';

        header('Content-Type: application/json');
        print(json_encode($return));
    }

} catch (Throwable $ex) {
    $return = new stdClass();
    $return->success = false;
    $return->msg = $ex->getMessage();

    header('Content-Type: application/json');
    print(json_encode($return));
}