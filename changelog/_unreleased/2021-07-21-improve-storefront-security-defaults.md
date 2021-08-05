---
title: Improve storefront security defaults
issue: NEXT-13300

 
---
# Core
* Added session config `cookie_samesite` to `lax` in `Core/Framework/Resources/config/packages/framework.yaml`
* Added header `Referrer-Policy` with value `strict-origin-when-cross-origin` in `Core/Framework/Routing/CoreSubscriber.php`
___
# Storefront
*  Added `secure` and `sameSite` properties to cookies in `Storefront/Resources/app/storefront/src/helper/storage/cookie-storage.helper.js`
