---
title: Handle unauthenticated app registration failure
issue: NEXT-20097
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\App\Exception\AppRegistrationException` to extend `Shopware\Core\Framework\ShopwareHttpException`
* Added `Shopware\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException`
* Changed `Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake::signPayload()` to throw `Shopware\Core\Framework\App\Exception\AppLicenseCouldNotBeVerifiedException`
___
# Administration
* Changed `src/module/sw-extension/service/index.js` to pass error codes to the `ExtensionErrorService` constructor
* Changed `src/module/sw-extension/service/extension-error.service.js` to handle `actions` and `autoClose` correctly for notifications
