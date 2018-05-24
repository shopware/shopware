<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSeoCategory\Event;

use Shopware\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
