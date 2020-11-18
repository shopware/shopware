---
title: Pass-through stamps in `MonitoringBusDecorator`
issue: NEXT-11406 
---
# Core
* Changed `\Shopware\Core\Framework\MessageQueue\MonitoringBusDecorator` to pass-through stamps to the decorated message bus. This makes it possible to pass meta information to the transport. This example configures a priority of a message for enqueue: `$bus->dispatch($message, [new TransportConfiguration(['metadata' => ['priority' => 4]])])` 
