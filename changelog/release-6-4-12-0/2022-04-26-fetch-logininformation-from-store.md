---
title: Fetch logininformation from store
issue: NEXT-20617
author: Sebastian Franze
author_email: s.franze@shopware.com
author_github: Sebastian Franze
---
# Core
* Added migration `Migration1650981517RemoveShopwareId`.
* Removed system config key `core.store.shopwareId`.
* Changed `Shopware\Core\Framework\Store\Services\StoreClient::loginWithShopwareId`. Method will not write system config entry `core.store.shopwareId` anymore.
* Changed `Shopware\Core\Framework\Store\Services\FirstRunWizardClient::frwLogin`. Method will not write system config entry `core.store.shopwareId` anymore.
* Added new parameter `user_info` to `shopware.store_endpoints`.
___
# API
* Changed Response of `/api/_action/store/checklogin`. Response now contains key `userInfo` with information about the sw acount.
___
# Administration
* Added new global types `ShopwareHttpError` and `StoreApiException`.
* Added new type `UserInfo` in `src/core/service/api/store.api.service.ts`.
* Changed `src/module/sw-extension/page/sw-extension-my-extensions-account/index.js` to `src/module/sw-extension/page/sw-extension-my-extensions-account/index.ts`.
* Deprecated method `loginShopwareUser` in `src/module/sw-extension/page/sw-extension-my-extensions-account/index.ts`. Use Method `login` instead
* Changed `src/module/sw-extension/service/extension-error-handler.service.js` to `src/module/sw-extension/service/extension-error-handler.service.ts`.
* Added new type `MappedError` in `src/module/sw-extension/service/extension-error-handler.service.ts`.
* Added new field `userInfo` in `ShopwareExtensionsState`.
* Added mutation `setUserInfo` in `ShopwareExtensionsState`.
* Deprecated field `shopwareId` in `ShopwareExtensionsState`. Check existence of `userInfo` instead.
* Deprecated field `loginStatus` in `ShopwareExtensionsState` Check existence of `userInfo` instead.
* Deprecated mutation `storeShopwareId` in `ShopwareExtensionsState`. Mutation will be removed without replacement.
* Deprecated mutation `setLoginStatus` in `ShopwareExtensionsState`. Mutation will be removed without replacement.
