---
title: Plugin configuration not removed when disabled or uninstalled
issue: NEXT-12350
---
# Core
*  Added new method `deletePluginConfiguration` in `src/Core/System/SystemConfig/SystemConfigService.php` to delete the configuration in the database
*  Added new method `filterNotActivatedPlugins` in `load` method of `src/Core/System/SystemConfig/SystemConfigService.php` to remove the config key of not activated plugins
*  Added `$this->systemConfigService->deletePluginConfiguration($pluginBaseClass);` in `uninstallPlugin` method of `src/Core/Framework/Plugin/PluginLifecycleService.php` to delete the configuration in the database when the user uninstall plugin
