---
title: Redis cart persister
date: 2022-03-25
area: checkout
tags: [checkout, cart, redis, performance]
---
With the last benchmarks it became clear how cost intensive the loading and saving of the shopping cart to and from the database is. A detailed analysis revealed two problems:

1) Every time the shopping cart is loaded, it is written back to the database after validation. However, this leads to a write on the connection which causes us to lose support for master-slave database setups.
2) To ensure the best possible performance, the shopping cart is written to the database as a serialized object. However, this in turn leads to rather high amounts of data having to be sent through the internal network.

To solve these problems, we implemented the `Shopware\Core\Checkout\Cart\RedisCartPersister`:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

class RedisCartPersister extends AbstractCartPersister
{
    /**
     * @var \Redis|\RedisCluster
     */
    private $redis;

    private EventDispatcherInterface $eventDispatcher;

    private bool $compress;

    public function load(string $token, SalesChannelContext $context): Cart {}

    public function save(Cart $cart, SalesChannelContext $context): void {}

    public function delete(string $token, SalesChannelContext $context): void {}

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void {}
}
```

This stores the cart inside redis, which can be configured via the config in `config/packages/*.yaml`:

```yaml
shopware:
    cart:
        redis_url: 'redis://redis'
```

If no redis connection is configured, the redis cart persister is removed from the DI container. This is done inside the `\Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass`:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection\CompilerPass;

class CartRedisCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('shopware.cart.redis_url')) {
            $container->removeDefinition('shopware.cart.redis');
            $container->removeDefinition(RedisCartPersister::class);

            return;
        }

        $container->removeDefinition(CartPersister::class);
        $container->setAlias(CartPersister::class, RedisCartPersister::class);
    }
}
```

In addition, to reduce network traffic, we used cache compression, which significantly reduces the amount of data to be sent. However, the compression can be deactivated again via `config/packages/*.yaml`:

```yaml
shopware:
    cart:
        compress: false
```

**Notice:** Currently there is no migration path to transfer shopping carts from one storage to the other.

