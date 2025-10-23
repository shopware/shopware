<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductContentLayoutEntity>
 */
#[Package('inventory')]
class ProductContentLayoutCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(fn (ProductContentLayoutEntity $entity) => $entity->getProductId());
    }

    /**
     * @return array<string>
     */
    public function getSalesChannelIds(): array
    {
        return $this->fmap(fn (ProductContentLayoutEntity $entity) => $entity->getSalesChannelId());
    }

    /**
     * @return array<string>
     */
    public function getContentLayoutIds(): array
    {
        return $this->fmap(fn (ProductContentLayoutEntity $entity) => $entity->getContentLayoutId());
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(fn (ProductContentLayoutEntity $entity) => $entity->getProductId() === $id);
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(fn (ProductContentLayoutEntity $entity) => $entity->getSalesChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductContentLayoutEntity::class;
    }
}
