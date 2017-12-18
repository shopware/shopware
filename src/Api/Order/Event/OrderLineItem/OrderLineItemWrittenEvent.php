<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderLineItem;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderLineItemDefinition;

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
