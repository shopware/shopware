---
title: Add new low_priority queue
issue: NEXT-31249
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added new messenger transport `low_priority` to `src/Core/Framework/Resources/config/packages/framework.yaml`
* Added new parameter `env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN)` to `src/Core/Framework/Resources/config/packages/framework.yaml` which defaults to Doctrine
* Added new subscriber `Shopware\Core\Framework\MessageQueue\Subscriber\ConsumeMessagesSubscriber` to automatically handle low_priority queue
___
# Upgrade Information
## Transport can be overridden on message level
If you explicitly configure a message to be transported via the `async` (default) queue, even though it implements the `LowPriorityMessageInterface` which would usually be transported via the `low_priority` queue, the transport is overridden for this specific message.

Example:
```php
<?php declare(strict_types=1);

namespace Your\Custom;

class LowPriorityMessage implements LowPriorityMessageInterface
{
}
```

```yaml
framework:
    messenger:
        routing:
            'Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface': low_priority
            'Your\Custom\LowPriorityMessage': async
```

## Configure another transport for the "low priority" queue
The transport defaults to use Doctrine. You can use the `MESSENGER_TRANSPORT_LOW_PRIORITY_DSN` environment variable to change it.

Before:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
```

After:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
    env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN): 'doctrine://default?auto_setup=false&queue_name=low_priority'
```

For further details on transports with different priorities, please refer to the Symfony Docs: https://symfony.com/doc/current/messenger.html#prioritized-transports

## Lower the priority for async messages
You might consider using the new `low_priority` queue if you are dispatching messages that do not need to be handled immediately.
To configure specific messages to be transported via the `low_priority` queue, you need to either adjust the routing or implement the `LowPriorityMessageInterface`:

```yaml
framework:
    messenger:
        routing:
            'Your\Custom\Message': low_priority
```

or

```php
<?php declare(strict_types=1);

namespace Your\Custom;

class Message implements LowPriorityMessageInterface
{
}
```

___
# Next Major Version Changes
## Configure queue workers to consume low_priority queue
Explicitly configure your workers to additionally consume messages from the `low_priority` queue.
Up to 6.6 the `low_priority` queue is automatically added to the workers, even if not specified explicitly.

Before:
```bash
php bin/console messenger:consume async
```

After:
```bash
php bin/console messenger:consume async low_priority
```

## Configure another transport for the "low priority" queue
The transport defaults to use Doctrine. You can use the `MESSENGER_TRANSPORT_LOW_PRIORITY_DSN` environment variable to change it.

Before:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
```

After:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
    env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN): 'doctrine://default?auto_setup=false&queue_name=low_priority'
```

For further details on transports with different priorities, please refer to the Symfony Docs: https://symfony.com/doc/current/messenger.html#prioritized-transports
