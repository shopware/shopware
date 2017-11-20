<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroup;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Customer\Definition\CustomerGroupDefinition;

class CustomerGroupWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDefinition::class;
    }
}
