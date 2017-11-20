<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroupDiscount;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Customer\Definition\CustomerGroupDiscountDefinition;

class CustomerGroupDiscountWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group_discount.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDiscountDefinition::class;
    }
}
