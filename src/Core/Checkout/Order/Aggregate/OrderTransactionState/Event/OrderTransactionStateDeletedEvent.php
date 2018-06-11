<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class OrderTransactionStateDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_transaction_state.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionStateDefinition::class;
    }
}
