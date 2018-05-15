<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransactionStateTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderTransactionStateTranslationDefinition;

class OrderTransactionStateTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_transaction_state_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderTransactionStateTranslationDefinition::class;
    }
}
