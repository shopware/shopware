---
title: Fix predis support
issue: NEXT-30364
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory::create()` to remove native return type, so all redis adapters supported by symfony are supported.
