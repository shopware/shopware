<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;

/**
 * @package content
 * @extends EntityCollection<CategoryEntity>
 */
class CategoryCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getParentId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getMediaId() === $id;
        });
    }

    public function sortByPosition(): self
    {
        $this->elements = AfterSort::sort($this->elements, 'afterCategoryId');

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(function (CategoryEntity $a, CategoryEntity $b) {
            return strnatcasecmp($a->getTranslated()['name'], $b->getTranslated()['name']);
        });

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'category_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryEntity::class;
    }
}
