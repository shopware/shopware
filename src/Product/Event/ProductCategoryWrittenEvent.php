<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductCategoryWrittenEvent extends WrittenEvent
{
    const NAME = 'product_category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_category';
    }
}
