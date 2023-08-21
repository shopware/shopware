---
title: Should be able to extend criteria before loading entity inside flow storer
issue: NEXT-26882
---
# Core
* Added new event `BeforeLoadStorableFlowDataEvent` at `Shopware\Core\Content\Flow\Events`, which is fired before loading an entity inside some flow storers
* Changed function `restore` to dispatch `BeforeLoadStorableFlowDataEvent` in the following files
    * `src/Core/Content/Flow/Dispatching/Storer/CustomerGroupStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/CustomerRecoveryStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/CustomerStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/NewsletterRecipientStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/OrderStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/OrderTransactionStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/ProductStorer.php`
    * `src/Core/Content/Flow/Dispatching/Storer/UserStorer.php`
___
# Upgrade Information
## Introduce BeforeLoadStorableFlowDataEvent
The event is dispatched before the flow storer restores the data, so you can customize the criteria before passing it to the entity repository

**Reference: Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent**

**Examples:**

```php
class OrderStorer extends FlowStorer
{
    public function restore(StorableFlow $storable): void
    {
        ...
        $criteria = new Criteria();
        $criteria->addAssociations([
            'orderCustomer',
            'lineItems.downloads.media',
        ]);
        $event = new BeforeLoadStorableFlowDataEvent(
            OrderDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $order = $this->orderRepository->search($criteria, $context)->get($orderId);
        ...
    }
}

class YourBeforeLoadStorableFlowOrderDataSubscriber implements EventSubscriberInterface
    public static function getSubscribedEvents()
    {
        return [
            'flow.storer.order.criteria.event' => 'handle',
        ];
    }

    public function handle(BeforeLoadStorableFlowDataEvent $event): void
    {
        $criteria = $event->getCriteria();
        
        // Add new association
        $criteria->addAssociation('tags');
    }
}
```
___
