<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderDelivery;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderDeliveryDefinition;

class OrderDeliveryWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_delivery.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDeliveryDefinition::class;
    }
}
