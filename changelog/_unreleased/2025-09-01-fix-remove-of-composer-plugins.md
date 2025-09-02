---
title: Fix removal of composer plugins
issue: #11855
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\PluginManagementService::deletePlugin()` to allow removing plugins that use `executeComposerCommands` when they are installed in `custom` folder, to allow removal of plugins installed over the Admin. However, plugins originally installed over composer directly (and therefore located into `vendor` folder) are still not removable via the Admin.
