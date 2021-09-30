---
title: Added abstraction for message_queue_stats
issue: NEXT-17372
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: @OliverSkroblin
---
# Core
* Added new `Shopware\Core\Framework\Increment\AbstractIncrementer` class which defines the access to the `increment` pool
* Added new `\Shopware\Core\Framework\Increment\IncrementGatewayRegistry`
* Added new configuration `shopware.increment` which allows defining different pools of increment gateway. We introduce 2 default pools: `message_queue` and `user_activity`. A pool can have different adapter, default adapters are:
    * `mysql` [default] - writes all stats into the mysql table `message_queue_stats`
    * `redis` - writes all stats into a configured redis 
        * requires `shopware.increment.message_queue.config.url` for redis connection
    * `array` - writes all stats into an array. Can be used to disable message queue stats feature
* Added new compiler pass `\Shopware\Core\Framework\Increment\IncrementerGatewayCompilerPass`, which allows to easily integrate new increment gateways
___
# API
* Added new route: `/api/_info/queue.json` which returns the `message_queue` pool stats
___
# Upgrade Information
## Implement a custom increment pool
If you want to use the default `mysql` or `redis` or `array` adapter, you can ignore this tutorial and just use `type: 'mysql' // or redis, array` in the config file

It is quite easy to implement a new pool or a new adapter for the `increment` gateway.
Simply provide a service with the prefix `shopware.increment.<your_pool>.gateway.` and the `type` as suffix.
This then gives the full service id, as with the `array` type: `shopware.increment.your_pool.gateway.array`.

Enclosed is the implementation for the array adapter, which should clarify the concept. The content of the adapter has been removed for clarity:
```ArrayIncrementer.php
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

class ArrayIncrementer extends AbstractIncrementer
{
    public function getDecorated(): AbstractIncrementer { }

    public function increment(string $cluster, string $key): void { }

    public function decrement(string $cluster, string $key): void {}

    public function reset(string $cluster, ?string $key): void { }

    public function list(string $cluster, int $limit = 5, int $offset = 0): array { }
    
    public function getPool(): string
    
    public function getConfig(): array
}
```

```services.xml
<service id="shopware.increment.your_pool.gateway.array" 
         class="Shopware\Core\Framework\Increment\ArrayIncrementer"/>
```

```shopware.yaml
shopware:
    increment:
        your_pool:
            type: 'array'
```

If the custom adapter requires additional configs, they can simply be added dynamically under `shopware.increment.your_pool.config`.
```shopware.yaml
shopware:
    increment:
        your_pool:
            type: 's3'
            config: 
                secret: '..'
                url: '...'
```
