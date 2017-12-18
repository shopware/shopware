<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductMedia;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductMediaDefinition;

class ProductMediaWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductMediaDefinition::class;
    }
}
