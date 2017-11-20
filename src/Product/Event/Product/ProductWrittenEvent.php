<?php declare(strict_types=1);

namespace Shopware\Product\Event\Product;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductDefinition;

class ProductWrittenEvent extends WrittenEvent
{
    const NAME = 'product.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }
}
