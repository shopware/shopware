<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
