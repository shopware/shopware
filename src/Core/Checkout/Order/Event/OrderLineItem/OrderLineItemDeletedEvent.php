<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderLineItem;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderLineItemDefinition;

class OrderLineItemDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_line_item.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderLineItemDefinition::class;
    }
}
