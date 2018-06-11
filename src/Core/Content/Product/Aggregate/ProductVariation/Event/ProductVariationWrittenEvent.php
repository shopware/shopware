<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVariation\Event;

use Shopware\Core\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class ProductVariationWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_variation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductVariationDefinition::class;
    }
}
