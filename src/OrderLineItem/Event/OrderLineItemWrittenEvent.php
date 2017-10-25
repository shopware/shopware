<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class OrderLineItemWrittenEvent extends EntityWrittenEvent
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
