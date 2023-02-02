[titleEn]: <>(DAL request change set)
[hash]: <>(article:dal_change_set)

To ensure the best possible write performance, the DAL does not generate change sets by default when writing data and only returns the data that was written. 
In certain cases, however, it is necessary to react to a specific change. An example is when the product of an order changes. 
In this case, the stock of the previous and the new product must be updated. The change sets can now be requested at the `WriteCommand` level. In the following example the change set is requested for all write operations on the entity `order_line_item` when the field `referenced_id` is written. 
The change set is then available in the `EntityWrittenEvent` or `PostWriteValidationEvent`.

```
<?php

namespace Shopware;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LineItemChangedListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PreWriteValidationEvent::class => 'triggerChangeSet',
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'lineItemWritten',
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => 'lineItemWritten'
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event)
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof ChangeSetAware) {
                continue;
            }

            if ($command->getDefinition()->getEntityName() !== OrderLineItemDefinition::ENTITY_NAME) {
                continue;
            }

            // in case of deletion we have to request the change set too
            if ($command instanceof DeleteCommand || $command->hasField('referenced_id')) {
                $command->requestChangeSet();
                continue;
            }
        }
    }

    public function lineItemWritten(EntityWrittenEvent $event)
    {
        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            $changeSet = $result->getChangeSet();
            if (!$changeSet) {
                continue;
            }

            $type = $changeSet->getBefore('type');

            if ($type !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            if (!$changeSet->hasChanged('referenced_id')) {
                continue;
            }

            $ids[] = $changeSet->getBefore('referenced_id');
            $ids[] = $changeSet->getAfter('referenced_id');
        }

        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        // now we can update all affected ids ...
    }
}
```
