<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransactionState;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderTransactionStateDefinition;

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
