---
title: Rewrite Hot Reload to support HTTPS
issue: NEXT-37871
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `start-hot-reload.js` that is used by command `composer run watch:storefront`
  * Added support for HTTPS and HTTP (using `http-proxy` internal)
  * Added new environment variables for **KEY** (`process.env.STOREFRONT_HTTPS_KEY_FILE`) and **CERT** (`process.env.STOREFRONT_HTTPS_CERTIFICATE_FILE`)
  * For caddy users, you need to set `NODE_EXTRA_CA_CERTS` to the caddy root certificate for node to trust the certificate
  * Fixed some issues with media images and thumbnail images when port was not used.
