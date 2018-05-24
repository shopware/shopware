<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class OrderLineItemWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_line_item.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderLineItemDefinition::class;
    }
}
