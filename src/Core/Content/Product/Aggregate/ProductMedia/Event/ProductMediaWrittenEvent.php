<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
