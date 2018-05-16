<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionState\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;

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
