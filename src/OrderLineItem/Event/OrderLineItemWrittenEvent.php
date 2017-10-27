<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Event;

use Shopware\Api\Write\WrittenEvent;

class OrderLineItemWrittenEvent extends WrittenEvent
{
    const NAME = 'order_line_item.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_line_item';
    }
}
