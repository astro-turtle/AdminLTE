/**
 * convert RFC 1342-like base64 strings to array buffer
 * @param {mixed} obj
 * @returns {undefined}
 */
function recursiveBase64StrToArrayBuffer(obj) {
    let prefix = '=?BINARY?B?';
    let suffix = '?=';
    if (typeof obj === 'object') {
        for (let key in obj) {
            if (typeof obj[key] === 'string') {
                let str = obj[key];
                if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                    str = str.substring(prefix.length, str.length - suffix.length);

                    let binary_string = window.atob(str);
                    let len = binary_string.length;
                    let bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++)        {
                        bytes[i] = binary_string.charCodeAt(i);
                    }
                    obj[key] = bytes.buffer;
                }
            } else {
                recursiveBase64StrToArrayBuffer(obj[key]);
            }
        }
    }
}

/**
 * Convert a ArrayBuffer to Base64
 * @param {ArrayBuffer} buffer
 * @returns {String}
 */
function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode( bytes[ i ] );
    }
    return window.btoa(binary);
}

async function registerWebauthNUser() {
    try {

        // Check browser support
        if (!window.fetch || !navigator.credentials || !navigator.credentials.create) {
            throw new Error('Browser not supported.');
        }

        // Step 0: Client initiates a request to server to register an authenticator
        // Step 1: The RP builds an instance of the PublicKeyCredentialCreationOptions
        // get create args
        let rep = await window.fetch('relyingparty.php?fn=getCreateArgs', {method:'GET', cache:'no-cache'});
        const createArgs = await rep.json();

        // error handling
        if (createArgs.success === false) {
            throw new Error(createArgs.msg || 'unknown error occured');
        }

        recursiveBase64StrToArrayBuffer(createArgs);

        // Step 2: The JavaScript client calls navigator.credentials.create(). User Interaction is here.
        // create credentials
        const cred = await navigator.credentials.create(createArgs);

        // Step 3: Before proceeding, the authenticator will ask for some form of user consent.

        // Step 4: The new public key, a credential id, and other attestation data are returned to the browser
        const authenticatorAttestationResponse = {
            transports: cred.response.getTransports  ? cred.response.getTransports() : null,
            clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
            attestationObject: cred.response.attestationObject ? arrayBufferToBase64(cred.response.attestationObject) : null
        };

        // Step 5: PublicKeyCredential which is returned to the RP to finalize the registration.
        // check auth on server side
        rep = await window.fetch('relyingparty.php?fn=processCreate', {
            method  : 'POST',
            body    : JSON.stringify(authenticatorAttestationResponse),
            cache   : 'no-cache'
        });
        const authenticatorAttestationServerResponse = await rep.json();

        // prompt server response
        if (authenticatorAttestationServerResponse.success) {
            window.alert(authenticatorAttestationServerResponse.msg || 'registration success');
        } else {
            throw new Error(authenticatorAttestationServerResponse.msg);
        }

    } catch (err) {
        window.alert(err.message || 'unknown error occured');
    }
}

async function checkWebauthNUser() {
    try {

        // Check browser support
        if (!window.fetch || !navigator.credentials || !navigator.credentials.create) {
            throw new Error('Browser not supported.');
        }

        // Step 0: The client initiates a request to authenticate on behalf of the user
        // Step 1: The RP builds an instance of the PublicKeyCredentialRequestOptions. It contains the challenge and the allowCredentials
        // get check args
        let rep = await window.fetch('relyingparty.php?fn=getGetArgs', {method:'GET',cache:'no-cache'});
        const getArgs = await rep.json();

        // error handling
        if (getArgs.success === false) {
            throw new Error(getArgs.msg);
        }

        // replace binary base64 data with ArrayBuffer
        recursiveBase64StrToArrayBuffer(getArgs);

        // Step 2: The JavaScript client calls navigator.credentials.get(). User Interaction is here.
        // check credentials with hardware
        const cred = await navigator.credentials.get(getArgs);

        // Step 3: The authenticator finds a credential that matches the Relying Party ID and prompts the user to consent to the authentication

        // Step 4: The authenticator returns the authenticatorData and assertion signature back to the browser.
        // create object for transmission to server
        const authenticatorAttestationResponse = {
            id: cred.rawId ? arrayBufferToBase64(cred.rawId) : null,
            clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
            authenticatorData: cred.response.authenticatorData ? arrayBufferToBase64(cred.response.authenticatorData) : null,
            signature: cred.response.signature ? arrayBufferToBase64(cred.response.signature) : null,
            userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
        };

        // Step 5: The browser resolves the Promise to a PublicKeyCredential that contains the AuthenticatorAssertionResponse which is returned to the RP to finalize the authentication
        // send to server
        rep = await window.fetch('relyingparty.php?fn=processGet', {
            method:'POST',
            body: JSON.stringify(authenticatorAttestationResponse),
            cache:'no-cache'
        });
        
        // Step 6: Server Validation
        const authenticatorAttestationServerResponse = await rep.json();
        if (authenticatorAttestationServerResponse.success) {
            window.location.replace("index.php");
        }

    } catch (err) {
        window.alert(err.message || 'unknown error occured');
    }
}

async function clearWebauthNRegistrations() {
    try {

        // Check browser support
        if (!window.fetch || !navigator.credentials || !navigator.credentials.create) {
            throw new Error('Browser not supported.');
        }

        let rep = await window.fetch('relyingparty.php?fn=clearRegistrations', {method:'GET',cache:'no-cache'});
        const response = await rep.json();

        // error handling
        if (response.success === false) {
            throw new Error(response.msg);
        }

    } catch (err) {
        window.alert(err.message || 'unknown error occured');
    }
}