---
title: Fix removal of theme data on extension uninstall
issue: NEXT-14937
author_github: @Dominik28111
---
# Core
* Changed method `Shopware\Core\Framework\Plugin\PluginLifecycleService::uninstallPlugin()` to use `ThemeLifecycleService` for data removal.
___
# Storefront
* Added method `Shopware\Storefront\Theme\ThemeLifecycleService::removeTheme()` to remove theme data.
