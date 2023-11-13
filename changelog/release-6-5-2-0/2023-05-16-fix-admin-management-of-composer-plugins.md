---
title: Fix Admin management of composer plugins
issue: NEXT-26782
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\Util\PluginFinder` to use local path and local composer info of plugins if they exist in the plugin directory, even if the plugin is managed by composer.
* Changed `\Shopware\Core\Framework\Store\Services\ExtensionDownloader::download()` and `\Shopware\Core\Framework\Store\Command\StoreDownloadCommand::validatePluginIsNotManagedByComposer()` to allow downloads if the plugin also exists in the plugin directory.
