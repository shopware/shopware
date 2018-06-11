<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\Event;

use Shopware\Core\Content\Product\Aggregate\ProductSeoCategory\ProductSeoCategoryDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
