<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderState;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Order\Definition\OrderStateDefinition;

class OrderStateWrittenEvent extends WrittenEvent
{
    const NAME = 'order_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderStateDefinition::class;
    }
}
