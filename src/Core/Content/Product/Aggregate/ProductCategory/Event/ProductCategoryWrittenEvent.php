<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCategory\Event;

use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
