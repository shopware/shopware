<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class OrderDeliveryPositionWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'order_delivery_position.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_delivery_position';
    }
}
