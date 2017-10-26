<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CategoryAvoidCustomerGroupWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'category_avoid_customer_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'category_avoid_customer_group';
    }
}
