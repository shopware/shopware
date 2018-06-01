<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\ProductContextPriceDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
