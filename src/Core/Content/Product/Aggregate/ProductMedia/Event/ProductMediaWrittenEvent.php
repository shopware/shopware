<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
