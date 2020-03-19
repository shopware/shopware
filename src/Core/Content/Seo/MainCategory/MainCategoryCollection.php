<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\MainCategory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(MainCategoryEntity $entity)
 * @method void                    set(string $key, MainCategoryEntity $entity)
 * @method MainCategoryEntity[]    getIterator()
 * @method MainCategoryEntity[]    getElements()
 * @method MainCategoryEntity|null get(string $key)
 * @method MainCategoryEntity|null first()
 * @method MainCategoryEntity|null last()
 */
class MainCategoryCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): MainCategoryCollection
    {
        return $this->filter(static function (MainCategoryEntity $mainCategory) use ($id) {
            return $mainCategory->getSalesChannelId() === $id;
        });
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
