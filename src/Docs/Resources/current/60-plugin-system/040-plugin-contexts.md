[titleEn]: <>(Plugin - Contexts)
[titleDe]: <>(Plugin - Contexts)
[wikiUrl]: <>(../plugin-system/plugin-contexts?category=shopware-platform-en/plugin-system)

## Overview
In this guide, you will learn what plugin contexts exist and what they are used for.

## InstallContext
The `InstallContext` holds the following information.

| Field                  | Usage                          |
|------------------------|--------------------------------|
| plugin                 | The plugin to install          |
| context                | The shop context               |
| currentShopwareVersion | The current `Shopware` version |
| currentPluginVersion   | The current plugin version     |


You can access this fields by their getter methods.

```php
$installContext->getPlugin();
$installContext->getContext;
$installContext->getCurrentPluginVersion();
$installContext->getCurrentShopwareVersion();
```

## UninstallContext
The `UninstallContext` holds the following information.

| Field                  | Usage                                                |
|------------------------|------------------------------------------------------|
| plugin                 | The plugin to uninstall                              |
| context                | The shop context                                     |
| currentShopwareVersion | The current `Shopware` version                       |
| currentPluginVersion   | The current plugin version                           |
| keepUserData           | Holds information if the User-Data should be deleted |


You can access this fields by their getter methods.

```php
$uninstallContext->getPlugin();
$uninstallContext->getContext;
$uninstallContext->getCurrentPluginVersion();
$uninstallContext->getCurrentShopwareVersion();
$uninstallContext->keepUserData();
```

## UpdateContext
The `UpdateContext` holds the following information.

| Field                  | Usage                           |
|------------------------|---------------------------------|
| plugin                 | The plugin to update            |
| context                | The shop context                |
| currentShopwareVersion | The current `Shopware` version  |
| currentPluginVersion   | The current plugin version      |
| updatePluginVersion    | The plugin version to update to |


You can access this fields by their getter methods.

```php
$updateContext->getPlugin();
$updateContext->getContext;
$updateContext->getCurrentPluginVersion();
$updateContext->getCurrentShopwareVersion();
$updateContext->getUpdatePluginVersion();
```

## ActivateContext
The `ActivateContext` holds the same information as the `InstallContext`.

## DeactivateContext
The `DeactivateContext` holds the same information as the `InstallContext`.