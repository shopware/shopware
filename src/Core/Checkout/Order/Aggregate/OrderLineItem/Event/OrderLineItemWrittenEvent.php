<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
