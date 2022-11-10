---
title: Deprecate MessageQueue Wrapper
issue: NEXT-24016
---
# Core
* Deprecated several classes in the `MessageQueue` component, as we will remove our wrapper around the symfony messenger and enqueue as well in 6.5.0. This mostly affects the retry handling and encryption we build ourselves previously.
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
