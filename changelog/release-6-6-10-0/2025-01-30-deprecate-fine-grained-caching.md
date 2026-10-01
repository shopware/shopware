---
title: Deprecate fine-grained caching
issue: NEXT-40494
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` and `\Shopware\Core\System\SystemConfig\SystemConfigService` to tag system configs per sales channel.
* Added `$salesChannelId` parameter to `\Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook`.
* Deprecated `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` as it will become internal in the future.
* Deprecated `\Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook` as it will become @final in the future.
* Deprecated all config values under `shopware.cache.tagging`
___ 
# Upgrade Information 
## SalesChannelId is available in SystemConfigChangedHook
The SalesChannelId is now available in the SystemConfigChangedHook (`app.config.changed`). The request formats now looks like this:*
```diff
{
  "changes": [...],
+  "salesChannelId": "00000"
}
```
