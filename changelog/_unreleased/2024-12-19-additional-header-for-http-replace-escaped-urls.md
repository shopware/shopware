---
title: Additional header for http, replace escaped URLs
issue: NEXT-40111
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `src/Storefront/Resources/app/storefront/build/start-hot-reload.js`
  * To check via `accept` headers if the current request is the HTML document. `sec-fetch-dest` is not available via http.
  * Make sure we also replace escaped URLs in the document body.
