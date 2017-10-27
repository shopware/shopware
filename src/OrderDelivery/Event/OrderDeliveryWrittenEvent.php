<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Event;

use Shopware\Api\Write\WrittenEvent;

class OrderDeliveryWrittenEvent extends WrittenEvent
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
