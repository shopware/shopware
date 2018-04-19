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

namespace Shopware\Product\Struct;

use Shopware\Framework\Struct\Collection;

class ProductCollection extends Collection
{
    /**
     * @var Product[]
     */
    protected $elements = [];

    public function add(Product $product): void
    {
        $this->elements[$this->getKey($product)] = $product;
    }

    public function remove(string $number): void
    {
        parent::doRemoveByKey($number);
    }

    public function removeElement(Product $product): void
    {
        parent::doRemoveByKey($this->getKey($product));
    }

    public function exists(Product $product): bool
    {
        return parent::has($this->getKey($product));
    }

    public function get(string $number): ? Product
    {
        if ($this->has($number)) {
            return $this->elements[$number];
        }

        return null;
    }

    public function getIdentifiers(): array
    {
        return $this->getKeys();
    }

    public function getProductUuids(): array
    {
        return $this->map(function (Product $product) {
            return $product->getUuid();
        });
    }

    //    public function getUnits(): UnitCollection
    //    {
    //        $units = $this->map(function (Product $product) {
    //            return $product->getUnit();
    //        });
    //
    //        return new UnitCollection(array_filter($units));
    //    }
    //
    //    public function getManufacturers(): ManufacturerCollection
    //    {
    //        $manufacturers = $this->map(function (Product $product) {
    //            return $product->getManufacturer();
    //        });
    //
    //        return new ManufacturerCollection(array_filter($manufacturers));
    //    }
    //
    //    public function getTaxes(): TaxCollection
    //    {
    //        $taxes = $this->map(function (Product $product) {
    //            return $product->getTax();
    //        });
    //
    //        return new TaxCollection(array_filter($taxes));
    //    }

    protected function getKey(Product $element): string
    {
        return $element->getNumber();
    }
}
