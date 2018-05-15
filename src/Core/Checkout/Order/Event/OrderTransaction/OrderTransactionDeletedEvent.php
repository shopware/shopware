<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransaction;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderTransactionDefinition;

class OrderTransactionDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_transaction.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionDefinition::class;
    }
}
