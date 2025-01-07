---
title: Validate migration timestamps
issue: NEXT-40051
---
# Core
* Added `\Shopware\Core\Framework\Migration\MigrationStep::getPlausibleCreationTimestamp()` to validate migration timestamps with 6.7, to ensure that the migration order is always deterministic.
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService::uninstallPlugin()` to remove plugin migrations from migration table before calling `uninstall`, to allow recovering in case of errors by rerunning the migrations.
___
# Next Major Version Changes
## Bulletproofing Plugin Migrations
### Creation timestamp is now validated
The returned timestamp `MigrationStep::getCreationTimestamp()` method is now validated, it needs to be between `1` and `2147483647` (the `max_int` value on 32-bit systems). This ensures that the migration order is always deterministic and prevents common errors when the method returns a higher number, 
that will silently be treated as max_int, leading to multiple migrations having the same creation timestamp, thus the execution order becomes random, which might lead to hard to debug errors while executing migrations.
### Plugin migrations are now removed before calling `uninstall()`
When `keepUserData` is set to false during plugin uninstall, the plugin is expected to clean up all DB tables the plugin created in the `unistall` method.
Now we are cleaning up the plugin migrations from the migration table before calling the `uninstall` method, so in case of an error, the plugin can be reinstalled and the migrations can be rerun.