---
title: Don't require already required/installed plugins
issue: NEXT-25847
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService` to only require plugins that are not already installed or required by composer.
