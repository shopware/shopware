<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVisibility;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductVisibilityEntity>
 */
#[Package('inventory')]
class ProductVisibilityCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(fn (ProductVisibilityEntity $visibility) => $visibility->getProductId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(fn (ProductVisibilityEntity $visibility) => $visibility->getProductId() === $id);
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(fn (ProductVisibilityEntity $visibility) => $visibility->getSalesChannelId() === $id);
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
