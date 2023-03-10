---
title: Cache stampede protection
date: 2022-03-25
area: core
tags: [core, cache, performance]
---
The Cache Stampede protection is a mechanism to prevent several users try to update this cache entry at the same time if a cache entry is no longer hot. This mechanism is very useful if there is a lot of load on the store and a cache entry is expired or invalidated. If there is no cache stampede protection on the system, and several users call a category listing at the same time, which is no longer in the cache, then all users would be let through to the database and the server could collapse under the load.

We have now integrated such a protection into all our services using the [`\Symfony\Contracts\Cache\CacheInterface` of symfony](https://symfony.com/blog/new-in-symfony-4-2-cache-stampede-protection). This mechanic is mainly used in our cached store api routes. Another positive side effect is that the code has become much more concise, since much is done within symfony:

## `CachedRuleLoader` before
```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedRuleLoader extends AbstractRuleLoader
{
    public const CACHE_KEY = 'cart_rules';

    private AbstractRuleLoader $decorated;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(AbstractRuleLoader $decorated, TagAwareAdapterInterface $cache, LoggerInterface $logger)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this->decorated;
    }

    public function load(Context $context): RuleCollection
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::CACHE_KEY);

                return $item->get();
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::CACHE_KEY);

        $rules = $this->getDecorated()->load($context);

        $item->set($rules);
        $this->cache->save($item);

        return $rules;
    }
}
```

## `CachedRuleLoader` after
```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Cache\CacheInterface;

class CachedRuleLoader extends AbstractRuleLoader
{
    public const CACHE_KEY = 'cart_rules';

    private AbstractRuleLoader $decorated;

    private CacheInterface $cache;

    public function __construct(AbstractRuleLoader $decorated, CacheInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this->decorated;
    }

    public function load(Context $context): RuleCollection
    {
        return $this->cache->get(self::CACHE_KEY, function () use ($context): RuleCollection {
            return $this->decorated->load($context);
        });
    }
}
```

However, since the service itself does not recognize whether it is a cache hit or miss, we have removed the corresponding logging.
