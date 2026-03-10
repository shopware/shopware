---
title: Interactive offcanvas cookies
issue: 9451
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Added new route `/cookie/groups` in `CookieController` to return cookie groups as JSON for dynamic JavaScript processing
* Changed `CookieRoute` to set the cookie-config-hash value in the cookie groups response
* Changed `CookieProvider` to add a new hidden cookie entry `cookie-config-hash` to the required cookie group
* Added new constant `COOKIE_ENTRY_CONFIG_HASH_COOKIE` in `CookieProvider` for the cookie-config-hash entry
___
# Storefront
* Changed `cookie-configuration.plugin.js`
  - to handle cookie groups and set technically required cookies dynamically.
  - to handle permission and reset scenarios.
  - to set a cookie-config-hash cookie to detect changes in the cookie configuration.
  - to restart the cookie consent flow if the cookie configuration has changed.
