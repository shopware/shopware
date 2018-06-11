<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\Event;

use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
