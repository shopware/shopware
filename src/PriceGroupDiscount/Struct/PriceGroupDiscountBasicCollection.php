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

namespace Shopware\PriceGroupDiscount\Struct;

use Shopware\Framework\Struct\Collection;

class PriceGroupDiscountBasicCollection extends Collection
{
    /**
     * @var PriceGroupDiscountBasicStruct[]
     */
    protected $elements = [];

    public function add(PriceGroupDiscountBasicStruct $priceGroupDiscount): void
    {
        $key = $this->getKey($priceGroupDiscount);
        $this->elements[$key] = $priceGroupDiscount;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(PriceGroupDiscountBasicStruct $priceGroupDiscount): void
    {
        parent::doRemoveByKey($this->getKey($priceGroupDiscount));
    }

    public function exists(PriceGroupDiscountBasicStruct $priceGroupDiscount): bool
    {
        return parent::has($this->getKey($priceGroupDiscount));
    }

    public function getList(array $uuids): PriceGroupDiscountBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? PriceGroupDiscountBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getUuid();
        });
    }

    public function getPriceGroupUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getPriceGroupUuid();
        });
    }

    public function filterByPriceGroupUuid(string $uuid): PriceGroupDiscountBasicCollection
    {
        return $this->filter(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) use ($uuid) {
            return $priceGroupDiscount->getPriceGroupUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): PriceGroupDiscountBasicCollection
    {
        return $this->filter(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) use ($uuid) {
            return $priceGroupDiscount->getCustomerGroupUuid() === $uuid;
        });
    }

    protected function getKey(PriceGroupDiscountBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
