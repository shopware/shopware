<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVisibility;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(ProductVisibilityEntity $entity)
 * @method void                         set(string $key, ProductVisibilityEntity $entity)
 * @method ProductVisibilityEntity[]    getIterator()
 * @method ProductVisibilityEntity[]    getElements()
 * @method ProductVisibilityEntity|null get(string $key)
 * @method ProductVisibilityEntity|null first()
 * @method ProductVisibilityEntity|null last()
 */
class ProductVisibilityCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductVisibilityEntity $visibility) {
            return $visibility->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductVisibilityEntity $visibility) use ($id) {
            return $visibility->getProductId() === $id;
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (ProductVisibilityEntity $visibility) use ($id) {
            return $visibility->getSalesChannelId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'product_visibility_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductVisibilityEntity::class;
    }
}
