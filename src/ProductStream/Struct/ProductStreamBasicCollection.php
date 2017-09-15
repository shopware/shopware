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

namespace Shopware\ProductStream\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;

class ProductStreamBasicCollection extends Collection
{
    /**
     * @var ProductStreamBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductStreamBasicStruct $productStream): void
    {
        $key = $this->getKey($productStream);
        $this->elements[$key] = $productStream;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductStreamBasicStruct $productStream): void
    {
        parent::doRemoveByKey($this->getKey($productStream));
    }

    public function exists(ProductStreamBasicStruct $productStream): bool
    {
        return parent::has($this->getKey($productStream));
    }

    public function getList(array $uuids): ProductStreamBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductStreamBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductStreamBasicStruct $productStream) {
            return $productStream->getUuid();
        });
    }

    public function getListingSortingUuids(): array
    {
        return $this->fmap(function (ProductStreamBasicStruct $productStream) {
            return $productStream->getListingSortingUuid();
        });
    }

    public function filterByListingSortingUuid(string $uuid): ProductStreamBasicCollection
    {
        return $this->filter(function (ProductStreamBasicStruct $productStream) use ($uuid) {
            return $productStream->getListingSortingUuid() === $uuid;
        });
    }

    public function getSortings(): ListingSortingBasicCollection
    {
        return new ListingSortingBasicCollection(
            $this->fmap(function (ProductStreamBasicStruct $productStream) {
                return $productStream->getSorting();
            })
        );
    }

    protected function getKey(ProductStreamBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
