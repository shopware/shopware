<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductMedia;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Product\Definition\ProductMediaDefinition;

class ProductMediaWrittenEvent extends WrittenEvent
{
    const NAME = 'product_media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductMediaDefinition::class;
    }
}
