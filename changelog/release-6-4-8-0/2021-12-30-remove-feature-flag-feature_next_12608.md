---
title: Remove feature flag FEATURE_NEXT_12608
issue: NEXT-17643
---
# Core
* Removed feature flag FEATURE_NEXT_12608
* Changed 7th parameter `$pluginLifecycleService` to `$extensionLifecycleService` in `Shopware\Core\Framework\Update\Api\UpdateController::__construct`. It will only accept object of type `Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle`.
* Removed 10th parameter `$extensionLifecycleService` in `Shopware\Core\Framework\Update\Api\UpdateController::__construct`.
* Changed `Shopware\Core\Framework\App\ScheduledTask\UpdateAppsHandler::__construct`. Parameter `$appUpdater` will only accept object of type `Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater`.
* Changed `Shopware\Core\Framework\Store\Api\StoreController::__construct`. Parameter `$extensionDataProvider` will only accept object of type `Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider`.
* Changed signature of `Shopware\Core\Framework\Store\Api\StoreController::getUpdateList`. Parameter `$request` was removed.
* Changed `Shopware\Core\Framework\Store\Services\StoreClient::__construct`.
  * Parameter `$optionsProvider` will only accept object of type `Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider`.
  * Parameter `$extensionLoader` will only accept object of type `Shopware\Core\Framework\Store\Services\ExtensionLoader`.
  * Parameter `$instanceService` will only accept object of type `Shopware\Core\Framework\Store\Services\InstanceService`.
* Removed `Shopware\Core\Framework\Store\Services\StoreClient::getUpdatesList`.
* Changed `Shopware\Core\Framework\Update\Services\PluginCompatibility::__construct`. Parameter `$extensionDataProvider` will only accept object of type `Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider`.
* Removed class `Shopware\Core\Framework\Update\Steps\DeactivatePluginsStep`.
* Removed test `Shopware\Core\Framework\Test\Update\Steps\DeactivatePluginsStep`.
* Removed `Shopware\Core\Framework\Store\Services\StoreService::getDefaultQueryParameters`.
* Removed `Shopware\Core\Framework\Store\Services\StoreService::getDefaultQueryParametersFromContext`.
* Removed `Shopware\Core\Framework\Store\Services\StoreService::getShopwareVersion`.
___
# Administration
* Removed feature flag FEATURE_NEXT_12608
* Removed feature flag condition from `sw-meteor-card`. The component is always registered now.
* Removed feature flag condition from `sw-meteor-navigation`. The component is always registered now.
* Removed feature flag condition from `sw-meteor-page`. The component is always registered now.
* Removed feature flag condition from `sw-license-violation::fetchPLugins`
* Deprecated computed property `pluginRepository` in `sw-license-violation`. It will be removed.
* Removed feature flag condition in `sw-extension/index`.
* Removed async import statements in `sw-extension/index`. Module components will now be imported synchronously.
* Removed registration of `sw-extension-error.mixin` from `sw-extension/index`.
* Removed default export in `sw-extension-error.mixin`. The registration of the mixin is a side effect of its import now.
* Deprecated computed property `cardTitle` in `sw-settings-shopware-updates-plugins`. It will be removed.
* Deprecated method `openPluginManager` in `sw-settings-shopware-updates-plugins`. It will be removed.
* Removed feature flag condition in `sw-shopware-updates-plugins.html.twig`.
