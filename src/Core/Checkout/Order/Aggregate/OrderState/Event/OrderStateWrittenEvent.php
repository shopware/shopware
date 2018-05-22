<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderState\Event;

use Shopware\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
