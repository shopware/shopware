<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransaction;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderTransactionDefinition;

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
