---
title:              Check app compatibility on auto update
issue:              NEXT-13261
author:             Ramona Schwering
author_email:       r.schwering@shopware.com
author_github:      @leichteckig
flag:                FEATURE_NEXT_12608
---
# Administration
* Added `openMyExtensions` method to `sw-shopware-updates-plugins.html.twig`
___
# Core
* Added `ExtensionLifecycleService` as argument to `Shopware\Core\Framework\Update\Api\UpdateController`
* Added `AbstractExtensionDataProvider` as argument to `Shopware\Core\Framework\Update\Services\ApiClient`
* Added `searchCriteria` as additional parameter to `getInstalledExtensions` in `\Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider`
* Added `getExtensionCompatibilities` method in `Shopware\Core\Framework\Store\Services\StoreClient` 
* Added `getExtensionCompatibilities` method in `Shopware\Core\Framework\Update\Services\PluginCompatibility` 
* Added `getExtensionsToDeactivate` method in `Shopware\Core\Framework\Update\Services\PluginCompatibility`
* Added `getExtensionsToReactivate` method in `Shopware\Core\Framework\Update\Services\PluginCompatibility`
* Added `fetchActiveExtensions` method in `Shopware\Core\Framework\Update\Services\PluginCompatibility`
* Added `fetchInactiveExtensions` method in `Shopware\Core\Framework\Update\Services\PluginCompatibility`
* Added `DeactivateExtensionsStep` class
* Removed `ReactivatePluginsStep` class due to it not being used
