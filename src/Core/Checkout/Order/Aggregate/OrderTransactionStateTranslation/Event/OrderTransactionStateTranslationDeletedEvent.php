<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
