<?php declare(strict_types=1);

namespace Shopware\Customer\Event\Customer;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Customer\Definition\CustomerDefinition;

class CustomerWrittenEvent extends WrittenEvent
{
    const NAME = 'customer.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerDefinition::class;
    }
}
