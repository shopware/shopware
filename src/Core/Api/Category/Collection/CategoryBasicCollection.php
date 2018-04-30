<?php declare(strict_types=1);

namespace Shopware\Api\Category\Collection;

use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CategoryBasicCollection extends EntityCollection
{
    /**
     * @var CategoryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CategoryBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CategoryBasicStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($id) {
            return $category->getParentId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($id) {
            return $category->getMediaId() === $id;
        });
    }

    public function getProductStreamIds(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getProductStreamId();
        });
    }

    public function filterByProductStreamId(string $id): self
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($id) {
            return $category->getProductStreamId() === $id;
        });
    }

    public function getSortingIds(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getSortingIds();
        });
    }

    public function filterBySortingIds(string $id): self
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($id) {
            return $category->getSortingIds() === $id;
        });
    }

    public function getFacetIds(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getFacetIds();
        });
    }

    public function filterByFacetIds(string $id): self
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($id) {
            return $category->getFacetIds() === $id;
        });
    }

    public function sortByPosition(): self
    {
        $this->sort(function (CategoryBasicStruct $a, CategoryBasicStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(function (CategoryBasicStruct $a, CategoryBasicStruct $b) {
            return strnatcasecmp($a->getName(), $b->getName());
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CategoryBasicStruct::class;
    }
}
