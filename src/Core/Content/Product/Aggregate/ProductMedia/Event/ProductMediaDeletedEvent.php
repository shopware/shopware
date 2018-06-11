<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia\Event;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
