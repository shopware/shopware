<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class OrderTransactionStateTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_transaction_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionStateTranslationDefinition::class;
    }
}
