<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupDiscount;

use Shopware\Api\Customer\Definition\CustomerGroupDiscountDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerGroupDiscountWrittenEvent extends WrittenEvent
{
    public const NAME = 'customer_group_discount.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDiscountDefinition::class;
    }
}
