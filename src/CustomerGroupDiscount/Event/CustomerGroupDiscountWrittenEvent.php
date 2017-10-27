<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Event;

use Shopware\Api\Write\WrittenEvent;

class CustomerGroupDiscountWrittenEvent extends WrittenEvent
{
    const NAME = 'customer_group_discount.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'customer_group_discount';
    }
}
