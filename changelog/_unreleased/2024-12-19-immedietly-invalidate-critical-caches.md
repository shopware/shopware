---
title: Immediately invalidate critical caches
issue: NEXT-40112
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` to force the immediate invalidation of the following caches:
  * `\Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader`
  * `\Shopware\Core\System\SystemConfig\CachedSystemConfigLoader`
  * `\Shopware\Core\Checkout\Cart\CachedRuleLoader`
  * `\Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory`
  * `\Shopware\Core\System\SalesChannel\Context\CachedBaseContextFactory`
