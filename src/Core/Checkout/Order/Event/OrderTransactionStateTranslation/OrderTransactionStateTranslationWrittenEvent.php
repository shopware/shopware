<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransactionStateTranslation;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderTransactionStateTranslationDefinition;

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
