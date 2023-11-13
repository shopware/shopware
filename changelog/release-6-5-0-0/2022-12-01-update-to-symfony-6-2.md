---
title: Update to symfony 6.2
issue: NEXT-24266
---
# Core
* Changed the version of all symfony components to `6.2`.
* Removed deprecated usage of `MessageSubscriberInterface` and `MessageHandlerInterface`
* Deprecated method `getHandledMessages()` in abstract `\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler`, all ScheduledTaskHandler need to be tagged with `#[AsMessageHandler]` instead.
___
# Next Major Version Changes
## Removal of `MessageSubscriberInterface` for `ScheduledTaskHandler`
The method `getHandledMessages()` in abstract class `\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler` was removed, please use the `#[AsMessageHandler]` attribute instead.

Before:
```php
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }
    
    public function run(): void
    {
        // ...
    }
}
```

After: 
```php
#[AsMessageHandler(handles: MyMessage::class)]
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        // ...
    }
}
```
