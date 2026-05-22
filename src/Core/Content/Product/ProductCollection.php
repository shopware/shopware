<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TElement of ProductEntity = ProductEntity
 *
 * @extends EntityCollection<TElement>
 */
#[Package('inventory')]
class ProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_collection';
    }

    /**
     * @return class-string<ProductEntity>
     */
    protected function getExpectedClass(): string
    {
        return ProductEntity::class;
    }
}
