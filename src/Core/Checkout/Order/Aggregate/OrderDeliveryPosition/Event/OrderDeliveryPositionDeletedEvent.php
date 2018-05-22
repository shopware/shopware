<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class OrderDeliveryPositionDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_delivery_position.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDeliveryPositionDefinition::class;
    }
}
