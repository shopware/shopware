<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductSeoCategory;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductSeoCategoryDefinition;

class ProductSeoCategoryDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_seo_category.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductSeoCategoryDefinition::class;
    }
}
