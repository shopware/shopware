<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event;

use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class OrderStateTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'order_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return OrderStateTranslationDefinition::class;
    }
}
