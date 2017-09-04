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

namespace Shopware\ProductManufacturer\Struct;

use Shopware\Framework\Struct\Collection;

class ProductManufacturerBasicCollection extends Collection
{
    /**
     * @var ProductManufacturerBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductManufacturerBasicStruct $productManufacturer): void
    {
        $key = $this->getKey($productManufacturer);
        $this->elements[$key] = $productManufacturer;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductManufacturerBasicStruct $productManufacturer): void
    {
        parent::doRemoveByKey($this->getKey($productManufacturer));
    }

    public function exists(ProductManufacturerBasicStruct $productManufacturer): bool
    {
        return parent::has($this->getKey($productManufacturer));
    }

    public function getList(array $uuids): ProductManufacturerBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductManufacturerBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (ProductManufacturerBasicStruct $productManufacturer) {
                return $productManufacturer->getUuid();
            }
        );
    }

    protected function getKey(ProductManufacturerBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
