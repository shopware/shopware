<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCategory\Event;

use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
