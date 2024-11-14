---
title: Fixed exception when shopware.cart_redis_url configuration parameter is set
issue: NEXT-39439

---
# Core
* Changed `Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass` to fix an exception that was thrown when the `shopware.cart_redis_url` configuration parameter was set.

