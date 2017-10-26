<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class OrderAddressWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'order_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'order_address';
    }
}
