<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
