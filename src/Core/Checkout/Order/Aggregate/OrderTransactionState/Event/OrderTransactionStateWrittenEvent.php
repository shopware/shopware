<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class OrderTransactionStateWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_transaction_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionStateDefinition::class;
    }
}
