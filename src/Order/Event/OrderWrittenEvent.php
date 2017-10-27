<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Api\Write\WrittenEvent;

class OrderWrittenEvent extends WrittenEvent
{
    const NAME = 'order.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order';
    }
}
