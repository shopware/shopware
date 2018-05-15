<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductContextPrice;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductContextPriceDefinition;

class ProductContextPriceWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_context_price.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductContextPriceDefinition::class;
    }
}
