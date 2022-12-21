---
title: Remove major deprecations from merchant services area
issue: NEXT-24646
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Administration
* Changed store `src/app/state/extensions.store.ts` to be private
* Removed deprecated component `src/module/sw-dashboard/component/sw-dashboard-external-link/index.js`
* Removed deprecations from component `src/module/sw-extension/component/sw-extension-card-base/index.js`
  * Removed property `extensionCanBeOpened`
  * Removed computed property `canBeOpened`
  * Removed computed property `openLinkInformation`
* Changed component `src/module/sw-extension/component/sw-extension-domains-modal/index.js` to be private
* Changed component `src/module/sw-extension/component/sw-extension-file-upload/index.js` to be private
* Changed component `src/module/sw-extension/component/sw-extension-my-extensions-listing-controls/index.js` to be private
* Changed component `src/module/sw-extension/component/sw-extension-permissions-details-modal/index.js` to be private
* Changed component `src/module/sw-extension/component/sw-extension-permissions-modal/index.js` to be private
* Changed component `src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal/index.js` to be private
* Changed mixin `src/module/sw-extension/mixin/sw-extension-error.mixin.js` to be private
* Removed deprecations from `src/module/sw-extension/page/sw-extension-my-extensions-account/index.ts`
  * Removed computed property `shopwareId`
  * Removed method `loginShopwareUser`
* Changed component `src/module/sw-extension/page/sw-extension-my-extensions-recommendation/index.js` to be private
* Changed service `src/module/sw-extension/service/extension-error-handler.service.ts` to be private
* Changed service `src/module/sw-extension/service/extension-error.service.js` to be private
* Removed deprecations from `src/module/sw-extension/service/extension-store-action.service.ts`
  * Removed method `basicHeaders`
* Changed service `src/module/sw-extension/service/shopware-extension.service.ts` to be private
* Removed deprecations from `src/module/sw-extension/service/shopware-extension.service.ts`
  * Constructor parameter `storeApiService` is now mandatory
  * Removed method `canBeOpened`
  * Changed previously public method `updateModules` to be private
  * Removed method `_getLinkToApp`
  * Removed method `_getAppFromStore`
  * Removed method `_appHasMainModule`
  * Removed method `_createLinkToModule`
  * Removed method `_orderByType`
* Changed store `src/module/sw-extension/store/extensions.store.ts` to be private
* Removed deprecations from `src/module/sw-extension/store/extensions.store.ts`
  * Removed property `shopwareId`
  * Removed property `loginStatus`
  * Removed property `licensedExtensions`
  * Removed mutator `loadLicensedExtensions`
  * Removed mutator `licensedExtensions`
  * Removed mutator `storeShopwareId`
  * Removed mutator `setLoginStatus`
  * Removed mutator `commitPlugins`
* Changed `src/module/sw-extension/store/index.ts` to be private
* Changed component `src/module/sw-first-run-wizard/page/index/index.js` to be private
* Changed component `src/module/sw-first-run-wizard/view/sw-first-run-wizard-markets/index.js` to be private
* Changed component `src/module/sw-settings-store/page/sw-settings-store/index.js` to be private
* Changed component `src/module/sw-settings-store/index.js` to be private
* 
___
# Storefront
* Removed deprecated GTM setup from `src/Storefront/Resources/app/storefront/src/plugin/google-analytics/google-analytics.plugin.js`
___
# Next Major Version Changes
## Deprecated component `sw-dashboard-external-link` has been removed
* Use component `sw-external-link` instead of `sw-dashboard-external-link`
