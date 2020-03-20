<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                           add(ProductCrossSellingAssignedProductsEntity $entity)
 * @method void                                           set(string $key, ProductCrossSellingAssignedProductsEntity $entity)
 * @method ProductCrossSellingAssignedProductsEntity[]    getIterator()
 * @method ProductCrossSellingAssignedProductsEntity[]    getElements()
 * @method ProductCrossSellingAssignedProductsEntity|null get(string $key)
 * @method ProductCrossSellingAssignedProductsEntity|null first()
 * @method ProductCrossSellingAssignedProductsEntity|null last()
 */
class ProductCrossSellingAssignedProductsCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ProductCrossSellingAssignedProductsEntity::class;
    }

    public function getApiAlias(): string
    {
        return 'product_cross_selling_assigned_products_collection';
    }
}
