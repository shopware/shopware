<?php declare(strict_types=1);
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
        return $this->fmap(
            function (CategoryBasicStruct $category) {
                return $category->getUuid();
            }
        );
    }

    public function getParentUuids(): array
    {
        return $this->fmap(
            function (CategoryBasicStruct $category) {
                return $category->getParentUuid();
            }
        );
    }

    public function filterByParentUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(
            function (CategoryBasicStruct $category) use ($uuid) {
                return $category->getParentUuid() === $uuid;
            }
        );
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(
            function (CategoryBasicStruct $category) {
                return $category->getMediaUuid();
            }
        );
    }

    public function filterByMediaUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(
            function (CategoryBasicStruct $category) use ($uuid) {
                return $category->getMediaUuid() === $uuid;
            }
        );
    }

    public function getProductStreamUuids(): array
    {
        return $this->fmap(
            function (CategoryBasicStruct $category) {
                return $category->getProductStreamUuid();
            }
        );
    }

    public function filterByProductStreamUuid(string $uuid): CategoryBasicCollection
    {
        return $this->filter(
            function (CategoryBasicStruct $category) use ($uuid) {
                return $category->getProductStreamUuid() === $uuid;
            }
        );
    }

    public function getCanonicalUrls(): SeoUrlBasicCollection
    {
        return new SeoUrlBasicCollection(
            $this->fmap(
                function (CategoryBasicStruct $category) {
                    return $category->getCanonicalUrl();
                }
            )
        );
    }

    protected function getKey(CategoryBasicStruct $element): string
    {
        return $element->getUuid();
    }

    public function sortByPosition(): CategoryBasicCollection
    {
        $this->sort(function(CategoryBasicStruct $a, CategoryBasicStruct $b) {
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

}
