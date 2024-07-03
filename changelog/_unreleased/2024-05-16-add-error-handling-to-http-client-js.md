---
title: Add error handling to HttpClient service
issue: NEXT-35756
---
# Storefront
* Added method `setErrorHandlingInternal` to `Storefront/Resources/app/storefront/src/service/http-client.service.js`
* Changed method `_registerOnLoaded` in `Storefront/Resources/app/storefront/src/service/http-client.service.js` to catch errors, timeouts and aborts when `errorHandlingInternal` is set to `true`
