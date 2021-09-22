---
title: Added abstraction for message_queue_stats
issue: NEXT-17372
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: @OliverSkroblin
---
# Core
* Added new `src/Core/Framework/MessageQueue/Monitoring/AbstractMonitoringGateway.php` class which defines the access to the message_queue_stats
* Added new configuration `shopware.queue.monitoring.type` which allows to switch between different implementation:
    * `mysql` [default] - writes all stats into the mysql table `message_queue_stats`
    * `redis` - writes all stats into a configured redis 
        * requires `shopware.queue.monitoring.config.url` for redis connection
    * `array` - writes all stats into an array. Can be used to disable message queue stats feature
* Added new compiler pass `\Shopware\Core\Framework\MessageQueue\Monitoring\QueueMonitoringAdapterCompilerPass`, which allows to easily integrate new monitoring types
___
# API
* Added new route: `/api/_info/queue.json` which returns the message queue statistics
___
# Upgrade Information
## Queue monitoring - implement custom type
It is quite easy to implement a new type for the `message_queue_stats` monitoring.
Simply provide a service with the prefix `shopware.queue.monitoring.gateway.` and the `type` as suffix.
This then gives the full service id, as with the `array` type: `shopware.queue.monitoring.gateway.array`.

Enclosed is the implementation for the array adpater, which should clarify the concept. The content of the adapter has been removed for clarity:
```ArrayMonitoringGateway.php
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class ArrayMonitoringGateway extends AbstractMonitoringGateway
{
    public function getDecorated(): AbstractMonitoringGateway { }

    public function increment(string $name): void { }

    public function decrement(string $name): void {}

    public function reset(string $name): void { }

    public function get(): array { }
}
```

```services.xml
<service id="shopware.queue.monitoring.gateway.array" 
         class="Shopware\Core\Framework\MessageQueue\Monitoring\ArrayMonitoringGateway"/>
```

```shopware.yaml
shopware:
    queue:
        monitoring:
            type: 'array'
```

If the custom adapter requires additional configs, they can simply be added dynamically under `shopware.queue.monitoring.config`.
```shopware.yaml
shopware:
    queue:
        monitoring:
            type: 's3'
            config: 
                secret: '..'
                url: '...'
```
