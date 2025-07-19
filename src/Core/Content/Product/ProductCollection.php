<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductEntity>
 */
#[Package('inventory')]
class ProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductEntity::class;
    }
}
