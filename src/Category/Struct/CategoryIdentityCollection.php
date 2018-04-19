<?php

namespace Shopware\Category\Struct;

use Shopware\Framework\Struct\Collection;

class CategoryIdentityCollection extends Collection
{
    /**
     * @var CategoryIdentity[]
     */
    protected $elements = [];

    public function add(CategoryIdentity $categoryIdentity): void
    {
        $key = $this->getKey($categoryIdentity);
        $this->elements[$key] = $categoryIdentity;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(CategoryIdentity $categoryIdentity): void
    {
        parent::doRemoveByKey($this->getKey($categoryIdentity));
    }

    public function exists(CategoryIdentity $categoryIdentity): bool
    {
        return parent::has($this->getKey($categoryIdentity));
    }

    public function get(int $id): ? CategoryIdentity
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getIds(): array
    {
        return $this->fmap(function(CategoryIdentity $categoryIdentity) {
            return $categoryIdentity->getId();
        });
    }

    protected function getKey(CategoryIdentity $element): int
    {
        return $element->getId();
    }

    public function getPaths(): array
    {
        return $this->map(function (CategoryIdentity $category) {
            return $category->getPath();
        });
    }

    public function getIdsIncludingPaths(): array
    {
        $ids = [];
        foreach ($this->elements as $category) {
            $ids[] = $category->getId();
            foreach ($category->getPath() as $id) {
                $ids[] = $id;
            }
        }

        return array_keys(array_flip($ids));
    }

    /**
     * @param int|null $parentId
     *
     * @return CategoryIdentity[]
     */
    public function getTree(?int $parentId): array
    {
        $result = [];
        foreach ($this->elements as $category) {
            if ($category->getParent() != $parentId) {
                continue;
            }
            $category->setChildren(
                $this->getTree($category->getId())
            );
            $result[] = $category;
        }

        return $result;
    }

    public function sortByPosition(): CategoryIdentityCollection
    {
        $this->sort(function(CategoryIdentity $a, CategoryIdentity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
        return $this;
    }
}