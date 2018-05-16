<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductCategoryTree\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;

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
