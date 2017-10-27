<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Event;

use Shopware\Api\Write\WrittenEvent;

class CustomerGroupWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'customer_group';
    }
}
