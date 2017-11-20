<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductSeoCategory;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductSeoCategoryDefinition;

class ProductSeoCategoryWrittenEvent extends WrittenEvent
{
    const NAME = 'product_seo_category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductSeoCategoryDefinition::class;
    }
}
