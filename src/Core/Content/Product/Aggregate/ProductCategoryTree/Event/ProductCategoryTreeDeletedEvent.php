<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductCategoryTree\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;

class ProductCategoryTreeDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_category_tree.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductCategoryTreeDefinition::class;
    }
}
