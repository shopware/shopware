<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductMedia;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductMediaDefinition;

class ProductMediaDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_media.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductMediaDefinition::class;
    }
}
