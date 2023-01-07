<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductCrossSellingAssignedProductsEntity>
 *
 * @package inventory
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

    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductCrossSellingAssignedProductsEntity $entity) {
            return $entity->getProductId();
        });
    }

    public function sortByPosition(): void
    {
        $this->sort(function (ProductCrossSellingAssignedProductsEntity $a, ProductCrossSellingAssignedProductsEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
    }
}
