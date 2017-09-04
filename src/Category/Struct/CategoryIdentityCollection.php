<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
        return $this->fmap(function (CategoryIdentity $categoryIdentity) {
            return $categoryIdentity->getId();
        });
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
        $this->sort(function (CategoryIdentity $a, CategoryIdentity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    protected function getKey(CategoryIdentity $element): int
    {
        return $element->getId();
    }
}
