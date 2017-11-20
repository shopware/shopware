<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderDeliveryPosition;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Order\Definition\OrderDeliveryPositionDefinition;

class OrderDeliveryPositionWrittenEvent extends WrittenEvent
{
    const NAME = 'order_delivery_position.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDeliveryPositionDefinition::class;
    }
}
