<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderState;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderStateDefinition;

class OrderStateDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_state.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderStateDefinition::class;
    }
}
