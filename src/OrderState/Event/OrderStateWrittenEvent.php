<?php declare(strict_types=1);

namespace Shopware\OrderState\Event;

use Shopware\Api\Write\WrittenEvent;

class OrderStateWrittenEvent extends WrittenEvent
{
    const NAME = 'order_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_state';
    }
}
