<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\Customer;

use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
