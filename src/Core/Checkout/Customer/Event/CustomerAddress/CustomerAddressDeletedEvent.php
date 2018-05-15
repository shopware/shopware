<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerAddress;

use Shopware\Checkout\Customer\Definition\CustomerAddressDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
