<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderState;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderStateDefinition;

class OrderStateWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderStateDefinition::class;
    }
}
