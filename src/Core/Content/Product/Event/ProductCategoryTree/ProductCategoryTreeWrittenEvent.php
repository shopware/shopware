<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductCategoryTree;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductCategoryTreeDefinition;

class ProductCategoryTreeWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_category_tree.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductCategoryTreeDefinition::class;
    }
}
