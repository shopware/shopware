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

namespace Shopware\ProductPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ProductPriceBasicCollection extends Collection
{
    /**
     * @var ProductPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductPriceBasicStruct $productPrice): void
    {
        $key = $this->getKey($productPrice);
        $this->elements[$key] = $productPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductPriceBasicStruct $productPrice): void
    {
        parent::doRemoveByKey($this->getKey($productPrice));
    }

    public function exists(ProductPriceBasicStruct $productPrice): bool
    {
        return parent::has($this->getKey($productPrice));
    }

    public function getList(array $uuids): ProductPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getUuid();
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getProductUuid() === $uuid;
        });
    }

    public function getProductDetailUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getProductDetailUuid();
        });
    }

    public function filterByProductDetailUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getProductDetailUuid() === $uuid;
        });
    }

    protected function getKey(ProductPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
