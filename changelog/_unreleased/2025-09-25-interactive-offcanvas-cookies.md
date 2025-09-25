---
title: Interactive offcanvas cookies
issue: 9451
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `cookie-configuration.plugin.js`
  - to handle cookie groups and set technically required cookies dynamically.
  - to handle permission and reset scenarios.
  - to set a cookie-config-hash cookie to detect changes in the cookie configuration.
  - to restart the cookie consent flow if the cookie configuration has changed.
