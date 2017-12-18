<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerAddress;

use Shopware\Api\Customer\Definition\CustomerAddressDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerAddressWrittenEvent extends WrittenEvent
{
    public const NAME = 'customer_address.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerAddressDefinition::class;
    }
}
