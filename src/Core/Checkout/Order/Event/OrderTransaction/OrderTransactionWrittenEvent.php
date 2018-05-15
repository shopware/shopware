<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransaction;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderTransactionDefinition;

class OrderTransactionWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_transaction.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionDefinition::class;
    }
}
