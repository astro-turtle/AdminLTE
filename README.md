# About this project
This fun project is a fork of the [Pi Hole Web Interace](https://github.com/pi-hole/AdminLTE) and enables using Passkeys as a method of authentication. This repo gives additional capabilites to:
- Register multiple Passkeys
- Login and authenticate using Passkeys
- Clear existing Passkeys

Under the hood, we use WebAuthn to power the interaction between our Passkeys, the browser and Pi Hole Web Interace for authentication.

**Please note:** 
- The term "Passkeys" used here implies **any** authenticator. Both platform & roaming authenticators should work fine.

# Credits
This repo was inspired by: https://github.com/lbuchs/WebAuthn

# Installation
Out of the box, Pi Hole Web GUI needs some modifications for WebAuthn to work, mainly that:
-  Web GUI is on `HTTPS`
-  Web GUI is accessed using domain name (rather than IP address), ie. using `https://pi.hole/`

## Option 1: Automated installation of Pi Hole with WebAuthn
1. Run automated installation: `curl -sSL https://raw.githubusercontent.com/astro-turtle/pi-hole/master/automated%20install/basic-install.sh | bash`
1. Update your DNS settings to Pi-Hole's IP Address.
1. Navigate to your local instance of Pi Hole `https://pi.hole/`

**Note**: You'd need to login first using the password set at installation to register the passkey. Log out and log back in to confirm passkey works.

## Option 2: Manual installation on to an existing Pi Hole instance
1. Clone repo into a new directory: `sudo git clone https://github.com/astro-turtle/AdminLTE.git /var/www/html/admin-webauthn`
1. Configure HTTPS on Pi Hole Web GUI. There are many ways to do this, for instance, you can follow an existing guide here [Configure Pi-hole SSL using a self-signed certificate](https://www.virtualizationhowto.com/2021/12/configure-pi-hole-ssl-using-a-self-signed-certificate/).
1. Ensure `www-data` user ownership of Web GUI directory: `sudo chown -R www-data:www-data /var/www/html/admin-webauthn`
1. Install PHP Composer with: `sudo apt install -y composer`
1. Install Webauthn PHP Libraries using Composer: `sudo -u www-data composer install -d /var/www/html/admin-webauthn`
1. Navigate to `https://pi.hole/admin-webauthn`
1. Login with the existing password to register the passkey. Log out and log back in to confirm passkey works.
1. **OPTIONAL:** Replace and rename Web GUI directory: `sudo rm -r /var/www/html/admin && sudo mv /var/www/html/admin-webauthn /var/www/html/admin`

# Screenshots
## Login with Passkey
TBA

## Register Passkey
Settings -> Passkeys -> Register New Passkey

Refresh the page manually. Page does not auto refresh.

TBA

## Clear Passkey
Settings -> Passkeys -> Clear All Registration

Refresh the page manually. Page does not auto refresh.

TBA
