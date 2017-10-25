<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductAvoidCustomerGroupWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_avoid_customer_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_avoid_customer_group';
    }
}
