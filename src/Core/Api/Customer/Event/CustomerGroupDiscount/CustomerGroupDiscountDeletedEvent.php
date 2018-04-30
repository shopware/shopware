<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupDiscount;

use Shopware\Api\Customer\Definition\CustomerGroupDiscountDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class CustomerGroupDiscountDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'customer_group_discount.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CustomerGroupDiscountDefinition::class;
    }
}
