<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\MainCategory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MainCategoryEntity>
 */
#[Package('sales-channel')]
class MainCategoryCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): MainCategoryCollection
    {
        return $this->filter(static fn (MainCategoryEntity $mainCategory) => $mainCategory->getSalesChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'seo_main_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return MainCategoryEntity::class;
    }
}
