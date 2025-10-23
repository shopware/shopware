<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CategoryContentLayoutEntity>
 */
#[Package('discovery')]
class CategoryContentLayoutCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCategoryIds(): array
    {
        return $this->fmap(fn (CategoryContentLayoutEntity $entity) => $entity->getCategoryId());
    }

    /**
     * @return array<string>
     */
    public function getSalesChannelIds(): array
    {
        return $this->fmap(fn (CategoryContentLayoutEntity $entity) => $entity->getSalesChannelId());
    }

    /**
     * @return array<string>
     */
    public function getContentLayoutIds(): array
    {
        return $this->fmap(fn (CategoryContentLayoutEntity $entity) => $entity->getContentLayoutId());
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(fn (CategoryContentLayoutEntity $entity) => $entity->getCategoryId() === $id);
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(fn (CategoryContentLayoutEntity $entity) => $entity->getSalesChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'category_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }
}
