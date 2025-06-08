---
title: Optionally re-enable prefering IPv4 over IPv6 in Node17+
issue: NEXT-24893
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Administration
* Added check for result order change in `src/Administration/Resources/app/administration/webpack.config.js` using `IPV4FIRST=1` in `.env` file to enable the admin watcher, even if the webserver rejects IPv6 requests
___
# Storefront
* Added check for result order change in `src/Storefront/Resources/app/storefront/webpack.config.js` using `IPV4FIRST=1` in `.env` file to enable the storefront watcher, even if the webserver rejects IPv6 requests
