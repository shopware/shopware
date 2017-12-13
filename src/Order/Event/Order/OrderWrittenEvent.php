<?php declare(strict_types=1);

namespace Shopware\Order\Event\Order;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Order\Definition\OrderDefinition;

class OrderWrittenEvent extends WrittenEvent
{
    const NAME = 'order.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }
}
