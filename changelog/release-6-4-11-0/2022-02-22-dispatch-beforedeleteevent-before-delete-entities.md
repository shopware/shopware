---
title: Dispatch BeforeDeleteEvent before delete entities
issue: NEXT-11600
---
# Core
* Added new event `Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent` to be dispatched before executing deleting entities commands
* Changed method `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway::executeCommands` to dispatch `BeforeDeleteEvent` and execute its hooks after successfully/unsuccessfully ran
* Added new subscriber `Shopware\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber` to dispatch `CustomerDeletedEvent` when customers are deleted
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute` to not dispatch `CustomerDeletedEvent` because `CustomerBeforeDeleteSubscriber` did the job centrally
___
# Upgrade Information
## Introduce BeforeDeleteEvent
The event is dispatched before delete commands are executed, so you can add success callbacks into the event when the delete command is successfully executed. Or you add error callbacks to the event when the execution meets some errors.

**Reference: Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent**

**Examples:**

```php
class YourBeforeDeleteEvent implements EventSubscriberInterface
    public static function getSubscribedEvents()
    {
        return [
            BeforeDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(BeforeDeleteEvent $event): void
    {
        $context = $event->getContext();
        
        // Delete ids of the given entity
        // At the given point, the ids are not deleted yet
        $ids = $event->getIds(CustomerDefinition::ENTITY_NAME);

        $event->addSuccess(function (): void {
            // Implement the hook when the entities got deleted successfully
            // At the given point, the $ids are deleted
        });

        $event->addError(function (): void {
            // At the given point, the $ids are not deleted due to failure
            // Implement the hook when the entities got deleted unsuccessfully
        });
    }
}
```
___
