<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderLineItem;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
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
