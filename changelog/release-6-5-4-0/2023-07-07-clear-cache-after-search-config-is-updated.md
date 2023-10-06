---
title: Clear cache after search config is updated
issue: NEXT-28431
---
# Core
* Changed method `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::getSubscribedEvents` to listen to `product_search_config.written` event, after search config is updated, the search cache and suggest cache should be cleared
