<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\Event;

use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
