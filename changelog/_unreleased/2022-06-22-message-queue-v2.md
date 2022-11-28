---
title: Message queue v2
issue: NEXT-21456
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Core
* Removed `\Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, use `MessageHandlerInterface` instead. The `handle` method of the affected classes must therefore be replaced with the `__invoke` method. Affected classes:
  * `\Shopware\Core\Framework\Adapter\Cache\CacheClearer`
  * `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmer`
  * `\Shopware\Core\Content\ImportExport\Message\DeleteFileHandler`
  * `\Shopware\Core\Content\Media\Message\DeleteFileHandler`
  * `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer`
  * `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry`
  * `\Shopware\Core\Content\Media\Message\GenerateThumbnailsHandler`
  * `\Shopware\Core\Content\ImportExport\Message\ImportExportHandler`
  * `\Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler`
  * `\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler`
  * `\Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler`
* Changed complete structure of `Shopware\Core\Framework\MessageQueue` domain:
  * Removed subdirectories `DeadMessage`, `Enqueue`, `Handle`, `Middleware`, `ScheduledTask\MessageQueue`
  * Changed different namespace within the domain to due the fact that there were only one class left inside a domain
* Added `\Shopware\Core\Framework\MessageQueue\AsyncMessageInterface` which identifies messages which are async by default.
* Removed `message_queue_stats` entity
* Removed `src/Core/Framework/Resources/config/packages/enqueue.yaml` configuration
* Changed message queue configuration inside `src/Core/Framework/Resources/config/packages/framework.yaml`
  * Changed `default_transport_name` to `v65`
  * Added `MESSENGER_TRANSPORT_DSN` and `MESSENGER_TRANSPORT_FAILURE_DSN` which are configured with a doctrine transport by default
  * Added new `messenger.transport.symfony_serializer` which uses json format to serialize messages
___
# Upgrade Information
## Changed default message behavior
By default, all messages which are dispatched via message queue, are handled synchronous. Before 6.5 we had a message queue decoration to change this default behavior to asynchronous. This decoration has now been removed. We provide a simple opportunity to restore the old behavior by implementing the `AsyncMessageInterface` interface to dispatch message synchronous. 

```php
class EntityIndexingMessage implements AsyncMessageInterface
{
    // ...
}
```

## Changed default queue name
Before 6.5 our default message queue transport name were `default`. We changed this to `v65` to ensure that application which are running with the 6.5 are not handling the message of the 6.4.

You are now able to configure own transports and dispatch message over your own transports by adding new transports within the `framework.messenger.transports` configuration. For more details, see official symfony documentation: https://symfony.com/doc/current/messenger.html

## Json encoded message queue messages
Before 6.5, we php-serialized all message queue messages and php-unserialize them. This causes different problems, and we decided to change this format to json. This format is also recommend from symfony and other open source projects. Due to this change, you may have to change your messages when you added some php objects to the message. If you have simple PHP objects within a message, the symfony serializer should be able to encode and decode your objects. For more information take a look to the offical symfony documentation: https://symfony.com/doc/current/messenger.html#serializing-messages