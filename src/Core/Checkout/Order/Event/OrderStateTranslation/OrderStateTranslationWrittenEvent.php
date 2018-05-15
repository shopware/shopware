<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderStateTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Checkout\Order\Definition\OrderStateTranslationDefinition;

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
