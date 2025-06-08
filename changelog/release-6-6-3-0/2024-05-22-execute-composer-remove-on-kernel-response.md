---
title: Execute composer remove on kernel response
issue: NEXT-34501
---
# Core
* Changed `\Shopware\Core\Framework\Plugin\PluginLifecycleService` to only execute the `composer remove` for composer plugins on kernel response in web requests, to prevent auto-loading issues when the plugin that is being removed subscribes to events that are triggered in the request context after the composer dependency is removed.
