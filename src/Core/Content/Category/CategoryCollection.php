<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;

class CategoryCollection extends EntityCollection
{
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

    public function getSortingIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getSortingIds();
        });
    }

    public function filterBySortingIds(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getSortingIds() === $id;
        });
    }

    public function getFacetIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getFacetIds();
        });
    }

    public function filterByFacetIds(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getFacetIds() === $id;
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
            return strnatcasecmp($a->getViewData()->getName(), $b->getViewData()->getName());
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CategoryEntity::class;
    }
}
