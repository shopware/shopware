<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductStreamFilterCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductStreamFilterEntity::class;
    }
}
