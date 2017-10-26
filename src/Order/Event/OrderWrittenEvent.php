<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class OrderWrittenEvent extends AbstractWrittenEvent
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
