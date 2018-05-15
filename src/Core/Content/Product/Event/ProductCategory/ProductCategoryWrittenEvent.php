<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductCategory;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductCategoryDefinition;

class ProductCategoryWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductCategoryDefinition::class;
    }
}
