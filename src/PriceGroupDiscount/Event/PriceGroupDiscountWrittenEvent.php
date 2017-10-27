<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Event;

use Shopware\Api\Write\WrittenEvent;

class PriceGroupDiscountWrittenEvent extends WrittenEvent
{
    const NAME = 'price_group_discount.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'price_group_discount';
    }
}
