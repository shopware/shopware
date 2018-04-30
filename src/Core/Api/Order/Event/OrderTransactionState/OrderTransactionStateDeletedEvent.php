<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionState;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderTransactionStateDefinition;

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
