<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
