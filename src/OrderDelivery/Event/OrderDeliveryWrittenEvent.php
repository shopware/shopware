<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class OrderDeliveryWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'order_delivery.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_delivery';
    }
}
