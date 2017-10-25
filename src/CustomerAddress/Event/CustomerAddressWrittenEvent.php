<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class CustomerAddressWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'customer_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'customer_address';
    }
}
