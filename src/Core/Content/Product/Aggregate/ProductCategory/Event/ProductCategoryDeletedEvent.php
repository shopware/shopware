<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductCategory\Event;

use Shopware\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ProductCategoryDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_category.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductCategoryDefinition::class;
    }
}
