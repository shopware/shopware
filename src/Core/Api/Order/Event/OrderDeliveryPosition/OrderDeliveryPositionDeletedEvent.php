<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderDeliveryPosition;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderDeliveryPositionDefinition;

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
