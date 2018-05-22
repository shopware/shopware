<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery\Event;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class OrderDeliveryDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_delivery.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDeliveryDefinition::class;
    }
}
