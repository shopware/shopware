<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class EventIdExtractor
{
    public function getOrderLineItemIds(EntityWrittenContainerEvent $generic): array
    {
        $ids = [];

        if ($event = $generic->getEventByDefinition(OrderLineItemDefinition::class)) {
            $ids = $event->getIds();
        }

        return $ids;
    }
}
