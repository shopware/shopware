<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(ProductCrossSellingEntity $entity)
 * @method void                           set(string $key, ProductCrossSellingEntity $entity)
 * @method ProductCrossSellingEntity[]    getIterator()
 * @method ProductCrossSellingEntity[]    getElements()
 * @method ProductCrossSellingEntity|null get(string $key)
 * @method ProductCrossSellingEntity|null first()
 * @method ProductCrossSellingEntity|null last()
 */
class ProductCrossSellingCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ProductCrossSellingEntity::class;
    }

    public function getApiAlias(): string
    {
        return 'product_cross_selling_collection';
    }
}
