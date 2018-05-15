<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroup;

use Shopware\Checkout\Customer\Definition\CustomerGroupDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CustomerGroupWrittenEvent extends WrittenEvent
{
    public const NAME = 'customer_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDefinition::class;
    }
}
