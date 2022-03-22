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
* Added `shopware.cart.compress` config option to configure the compression of the cart data. This is not taken into account in the sql persister
* Deprecated `\Shopware\Core\Checkout\Cart\CartPersisterInterface`, use `\Shopware\Core\Checkout\Cart\AbstractCartPersister` instead