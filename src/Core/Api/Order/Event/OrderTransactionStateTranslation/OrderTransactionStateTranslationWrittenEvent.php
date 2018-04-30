<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionStateTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Order\Definition\OrderTransactionStateTranslationDefinition;

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
