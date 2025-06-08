---
title: Add config.xml for apps
issue: NEXT-11720
author: Jonas Elfering
---
# Core
* Added `\Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader::getConfiguration()` method to load config.xml files from Apps.
* Changed the `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle`'s `install()` and `update()` method to save default config values from config.xml files of the app.
* Changed the `\Shopware\Core\System\SystemConfig\Service\ConfigurationService::getConfiguration()` method, to be able to also fetch the configuration of installed apps.
* Added `configurable` property to `\Shopware\Core\Framework\App\AppDefinition` to indicate if an app provides a config.xml file.
