[titleEn]: <>(Plugin - Contexts)
[hash]: <>(article:plugin_contexts)

## Overview
In this guide, you will learn which plugin contexts exist and what they are used for.

## InstallContext

The `InstallContext` holds the following information.

| Field                  | Type                             | Usage                          |
|------------------------|----------------------------------|--------------------------------|
| plugin                 | \Shopware\Core\Framework\Plugin  | The plugin to install          |
| context                | \Shopware\Core\Framework\Context | The shop context               |
| currentShopwareVersion | string                           | The current `Shopware` version |
| currentPluginVersion   | string                           | The current plugin version     |


You can access this fields by their getter methods.

```php
$installContext->getPlugin();
$installContext->getContext;
$installContext->getCurrentPluginVersion();
$installContext->getCurrentShopwareVersion();
```

## UninstallContext

The `UninstallContext` holds the following information.

| Field                  | Type                             | Usage                                                |
|------------------------|----------------------------------|------------------------------------------------------|
| plugin                 | \Shopware\Core\Framework\Plugin  | The plugin to uninstall                              |
| context                | \Shopware\Core\Framework\Context | The shop context                                     |
| currentShopwareVersion | string                           | The current `Shopware` version                       |
| currentPluginVersion   | string                           | The current plugin version                           |
| keepUserData           | bool                             | Holds information if the User-Data should be deleted |


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

| Field                  | Type                             | Usage                           |
|------------------------|----------------------------------|---------------------------------|
| plugin                 | \Shopware\Core\Framework\Plugin  | The plugin to update            |
| context                | \Shopware\Core\Framework\Context | The shop context                |
| currentShopwareVersion | string                           | The current `Shopware` version  |
| currentPluginVersion   | string                           | The current plugin version      |
| updatePluginVersion    | string                           | The plugin version to update to |


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
