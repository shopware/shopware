<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\Order;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderDefinition;

class OrderDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }
}
