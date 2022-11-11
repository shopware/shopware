---
title: Update symfony to 6.1
issue: NEXT-23917
---
# Core
* Changed the minimum required PHP version to 8.1.
* Changed the used symfony version to 6.1, and symfony contracts v3.1.
* Changed the used ElasticSearch DSL library to `shyim/opensearch-php-dsl`, instead of `ongr/elasticsearch-dsl`.
* Deprecated `Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, use `\Symfony\Component\Messenger\Handler\MessageSubscriberInterface` directly instead.
___ 
# Upgrade Information
### MessageQueue Deprecations

For v6.5.0.0 we will remove our wrapper around the symfony messenger component and remove the enqueue integration as well. Therefore, we deprecated several classes for the retry and encryption handling, without replacement, as we  will use the symfony standards for that.

Additionally, we deprecated the `Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, you should directly implement the `\Symfony\Component\Messenger\Handler\MessageSubscriberInterface` instead.

Before:
```php
class MyMessageHandler extends AbstractMessageHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function handle(MyMessage $message): void
    {
        // do something
    }
}
```

After:
```php
class MyMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function __invoke(MyMessage $message): void
    {
        // do something
    }
}
```
