<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\ORM\EntityCollection;

class CategoryCollection extends EntityCollection
{
    /**
     * @var CategoryStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CategoryStruct
    {
        return parent::get($id);
    }

    public function current(): CategoryStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (CategoryStruct $category) {
            return $category->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (CategoryStruct $category) use ($id) {
            return $category->getParentId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (CategoryStruct $category) {
            return $category->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (CategoryStruct $category) use ($id) {
            return $category->getMediaId() === $id;
        });
    }

    public function getSortingIds(): array
    {
        return $this->fmap(function (CategoryStruct $category) {
            return $category->getSortingIds();
        });
    }

    public function filterBySortingIds(string $id): self
    {
        return $this->filter(function (CategoryStruct $category) use ($id) {
            return $category->getSortingIds() === $id;
        });
    }

    public function getFacetIds(): array
    {
        return $this->fmap(function (CategoryStruct $category) {
            return $category->getFacetIds();
        });
    }

    public function filterByFacetIds(string $id): self
    {
        return $this->filter(function (CategoryStruct $category) use ($id) {
            return $category->getFacetIds() === $id;
        });
    }

    public function sortByPosition(): self
    {
        $this->sort(function (CategoryStruct $a, CategoryStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(function (CategoryStruct $a, CategoryStruct $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CategoryStruct::class;
    }
}
