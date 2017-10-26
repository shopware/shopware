<?php declare(strict_types=1);

namespace Shopware\OrderState\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class OrderStateTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'order_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_state_translation';
    }
}
