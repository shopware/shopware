---
title: Add service for determining delta of app permissions and domains
issue: NEXT-18876
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added abstract class `Shopware\Core\Framework\App\Delta\AbstractAppConfirmationDeltaProvider`
  * Added service `Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider`
  * Added service `Shopware\Core\Framework\App\Delta\PermissionsDeltaService`
* Added exception `Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
* Deprecated exception `Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`, will be replaced with `Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
  * Deprecated `Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException` to only extend from `Shopware\Core\Framework\ShopwareHttpException`
Changed `Shopware\Core\Framework\App\Lifecycle\Update\AppUpdater` to catch new `Shopware\Core\Framework\Store\Exception\ExtensionUpdateRequiresConsentAffirmationException`
* Deprecated `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService`, will be marked as internal
  * Changed `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService` to use new `Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider`
  * Deprecated method `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService::getAppIdByName()`
___
# Next Major Version Changes
## Deprecations in `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService`
The class `StoreAppLifecycleService` has been marked as internal.

We also removed the `StoreAppLifecycleService::getAppIdByName()` method.

## Removal of `Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`
We removed the `ExtensionRequiresNewPrivilegesException` exception.
Will be replaced with the internal `ExtensionUpdateRequiresConsentAffirmationException` exception to have a more generic one.
