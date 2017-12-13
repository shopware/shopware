<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductCategoryTree;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Product\Definition\ProductCategoryTreeDefinition;

class ProductCategoryTreeWrittenEvent extends WrittenEvent
{
    const NAME = 'product_category_tree.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductCategoryTreeDefinition::class;
    }
}
