<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductStream;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductStreamDefinition;

class ProductStreamWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_stream.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamDefinition::class;
    }
}
