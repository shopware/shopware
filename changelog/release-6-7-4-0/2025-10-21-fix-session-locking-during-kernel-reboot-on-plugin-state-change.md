---
title: Fix session locking during kernel reboot on plugin state change
issue: https://github.com/shopware/shopware/issues/12823
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService::rebuildContainerWithNewPluginState` to save session, so session lock is released before kernel reboot.
