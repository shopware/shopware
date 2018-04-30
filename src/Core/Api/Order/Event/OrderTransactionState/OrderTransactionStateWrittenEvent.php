<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionState;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderTransactionStateDefinition;

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
