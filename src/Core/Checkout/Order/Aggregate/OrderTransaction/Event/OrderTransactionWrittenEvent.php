<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
