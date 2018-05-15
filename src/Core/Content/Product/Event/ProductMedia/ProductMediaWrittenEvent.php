<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductMedia;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductMediaDefinition;

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
