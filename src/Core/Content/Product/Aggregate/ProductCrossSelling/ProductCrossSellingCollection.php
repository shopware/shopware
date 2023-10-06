<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductCrossSellingEntity>
 */
#[Package('inventory')]
class ProductCrossSellingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_cross_selling_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductCrossSellingEntity::class;
    }
}
