<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;

class CategoryBasicCollection extends Collection
{
    /**
     * @var CategoryBasicStruct[]
     */
    protected $elements = [];

    public function add(CategoryBasicStruct $category): void
    {
        $key = $this->getKey($category);
        $this->elements[$key] = $category;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CategoryBasicStruct $category): void
    {
        parent::doRemoveByKey($this->getKey($category));
    }

    public function exists(CategoryBasicStruct $category): bool
    {
        return parent::has($this->getKey($category));
    }

    public function getList(array $uuids): CategoryBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CategoryBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CategoryBasicStruct $category) {
            return $category->getUuid();
        });
    }

    public function merge(CategoryBasicCollection $collection)
    {
        /** @var CategoryBasicStruct $category */
        foreach ($collection as $category) {
            if ($this->has($this->getKey($category))) {
                continue;
            }
            $this->add($category);
        }
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

    public function getSortingUuidss(): array
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

    public function getFacetUuidss(): array
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

    public function getCanonicalUrls(): SeoUrlBasicCollection
    {
        return new SeoUrlBasicCollection(
            $this->fmap(function (CategoryBasicStruct $category) {
                return $category->getCanonicalUrl();
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

    /**
     * @param string|null $parentUuid
     *
     * @return CategoryBasicStruct[]
     */
    public function getTree(?string $parentUuid): array
    {
        $result = [];
        foreach ($this->elements as $category) {
            if ($category->getParentUuid() !== $parentUuid) {
                continue;
            }
            $category->setChildren(
                $this->getTree($category->getUuid())
            );
            $result[] = $category;
        }

        return $result;
    }

    protected function getKey(CategoryBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
