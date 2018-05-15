<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderDeliveryPosition;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderDeliveryPositionDefinition;

class OrderDeliveryPositionWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_delivery_position.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDeliveryPositionDefinition::class;
    }
}
