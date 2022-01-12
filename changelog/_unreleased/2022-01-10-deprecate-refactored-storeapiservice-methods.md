---
title: Deprecate refactored StoreApiService methods
issue: NEXT-16321
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Deprecated method `Shopware\Core\Framework\Store\Api\StoreController::downloadPlugin()`
___
# API
* Deprecated route `api.custom.store.download` in favor of `api.extension.download`
___
# Administration
* Deprecated method `downloadPlugin()` in `src/core/service/api/store.api.service.js`
* Deprecated method `downloadAndUpdatePlugin()` in `src/core/service/api/store.api.service.js`
* Removed dependency on `StoreApiService` in `src/app/service/extension-helper.service.js` constructor
* Changed usage of `StoreApiService` to `ExtensionStoreActionService` in `src/module/sw-first-run-wizard/view/sw-first-run-wizard-data-import/index.js`
* Changed usage of `StoreApiService` to `ExtensionStoreActionService` in `src/module/sw-first-run-wizard/view/sw-first-run-wizard-paypal-info/index.js`
* Changed usage of `StoreApiService` to `ExtensionStoreActionService` in `src/module/sw-first-run-wizard/view/sw-first-run-wizard-welcome/index.js`
