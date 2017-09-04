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

namespace Shopware\Product\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;
use Shopware\ProductManufacturer\Struct\ProductManufacturerBasicCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;
use Shopware\Tax\Struct\TaxBasicCollection;

class ProductBasicCollection extends Collection
{
    /**
     * @var ProductBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductBasicStruct $product): void
    {
        $key = $this->getKey($product);
        $this->elements[$key] = $product;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductBasicStruct $product): void
    {
        parent::doRemoveByKey($this->getKey($product));
    }

    public function exists(ProductBasicStruct $product): bool
    {
        return parent::has($this->getKey($product));
    }

    public function getList(array $uuids): ProductBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getUuid();
        });
    }

    public function getManufacturerUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getManufacturerUuid();
        });
    }

    public function filterByManufacturerUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getManufacturerUuid() === $uuid;
        });
    }

    public function getTaxUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getTaxUuid();
        });
    }

    public function filterByTaxUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getTaxUuid() === $uuid;
        });
    }

    public function getMainDetailUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getMainDetailUuid();
        });
    }

    public function filterByMainDetailUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getMainDetailUuid() === $uuid;
        });
    }

    public function getFilterGroupUuids(): array
    {
        return $this->fmap(function (ProductBasicStruct $product) {
            return $product->getFilterGroupUuid();
        });
    }

    public function filterByFilterGroupUuid(string $uuid): ProductBasicCollection
    {
        return $this->filter(function (ProductBasicStruct $product) use ($uuid) {
            return $product->getFilterGroupUuid() === $uuid;
        });
    }

    public function getManufacturers(): ProductManufacturerBasicCollection
    {
        return new ProductManufacturerBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getManufacturer();
            })
        );
    }

    public function getMainDetails(): ProductDetailBasicCollection
    {
        return new ProductDetailBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getMainDetail();
            })
        );
    }

    public function getTaxs(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getTax();
            })
        );
    }

    public function getCanonicalUrls(): SeoUrlBasicCollection
    {
        return new SeoUrlBasicCollection(
            $this->fmap(function (ProductBasicStruct $product) {
                return $product->getCanonicalUrl();
            })
        );
    }

    protected function getKey(ProductBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
