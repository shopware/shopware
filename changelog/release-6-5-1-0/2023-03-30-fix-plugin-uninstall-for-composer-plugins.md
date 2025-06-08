---
title: Fix plugin uninstall for composer plugins
issue: NEXT-26059
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService::uninstallPlugin` to run the composer commands after all plugin lifecycle hooks are executed, thus fixing issues when trying to uninstall composer plugins.
