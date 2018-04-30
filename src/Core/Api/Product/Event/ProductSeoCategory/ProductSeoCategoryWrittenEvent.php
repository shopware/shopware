<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductSeoCategory;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductSeoCategoryDefinition;

class ProductSeoCategoryWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_seo_category.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductSeoCategoryDefinition::class;
    }
}
