<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\Product;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductDefinition;

class ProductWrittenEvent extends WrittenEvent
{
    public const NAME = 'product.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }
}
