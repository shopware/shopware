<?php declare(strict_types=1);

namespace Shopware\Category\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Media\Collection\MediaBasicCollection;

class CategoryBasicCollection extends EntityCollection
{
    /**
     * @var CategoryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CategoryBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CategoryBasicStruct
    {
        return parent::current();
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($uuid) {
            return $category->getParentUuid() === $uuid;
        });
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getMediaUuid();
        });
    }

    public function filterByMediaUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($uuid) {
            return $category->getMediaUuid() === $uuid;
        });
    }

    public function getProductStreamUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getProductStreamUuid();
        });
    }

    public function filterByProductStreamUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($uuid) {
            return $category->getProductStreamUuid() === $uuid;
        });
    }

    public function getSortingUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getSortingUuids();
        });
    }

    public function filterBySortingUuids(string $uuid): CategoryBasicCollection
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($uuid) {
            return $category->getSortingUuids() === $uuid;
        });
    }

    public function getFacetUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getFacetUuids();
        });
    }

    public function filterByFacetUuids(string $uuid): CategoryBasicCollection
    {
        return $this->filter(function (CategoryBasicStruct $category) use ($uuid) {
            return $category->getFacetUuids() === $uuid;
        });
    }

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (CategoryBasicStruct $category) {
                return $category->getMedia();
            })
        );
    }

    public function sortByPosition(): CategoryBasicCollection
    {
        $this->sort(function (CategoryBasicStruct $a, CategoryBasicStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CategoryBasicStruct::class;
    }
}
