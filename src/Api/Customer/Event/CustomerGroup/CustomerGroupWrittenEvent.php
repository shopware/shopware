<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroup;

use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
