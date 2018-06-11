<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVariation\Event;

use Shopware\Core\Content\Product\Aggregate\ProductVariation\ProductVariationDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class ProductVariationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_variation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductVariationDefinition::class;
    }
}
