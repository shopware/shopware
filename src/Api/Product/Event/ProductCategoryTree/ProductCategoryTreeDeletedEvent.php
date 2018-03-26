<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductCategoryTree;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryTreeDefinition;

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
