<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductVariation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductVariationDefinition;

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
