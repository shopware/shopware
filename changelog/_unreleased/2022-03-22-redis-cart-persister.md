---
title: Redis cart persister
issue: NEXT-20672
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\Checkout\Cart\RedisCartPersister`, which allows to persist the carts in Redis.
* Added `shopware.cart.redis_url` config option to configure the Redis URL for the cart persister.
