<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerAddress;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Customer\Definition\CustomerAddressDefinition;

class CustomerAddressWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerAddressDefinition::class;
    }
}
