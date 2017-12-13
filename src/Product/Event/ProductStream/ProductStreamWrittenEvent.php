<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductStream;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Product\Definition\ProductStreamDefinition;

class ProductStreamWrittenEvent extends WrittenEvent
{
    const NAME = 'product_stream.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamDefinition::class;
    }
}
