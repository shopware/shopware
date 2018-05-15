<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderStateTranslation;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderStateTranslationDefinition;

class OrderStateTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'order_state_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderStateTranslationDefinition::class;
    }
}
