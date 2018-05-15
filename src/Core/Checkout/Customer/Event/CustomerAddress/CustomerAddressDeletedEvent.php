<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerAddress;

use Shopware\Checkout\Customer\Definition\CustomerAddressDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerAddressDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'customer_address.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerAddressDefinition::class;
    }
}
