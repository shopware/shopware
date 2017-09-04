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

namespace Shopware\ShippingMethodPrice\Struct;

use Shopware\Framework\Struct\Collection;

class ShippingMethodPriceBasicCollection extends Collection
{
    /**
     * @var ShippingMethodPriceBasicStruct[]
     */
    protected $elements = [];

    public function add(ShippingMethodPriceBasicStruct $shippingMethodPrice): void
    {
        $key = $this->getKey($shippingMethodPrice);
        $this->elements[$key] = $shippingMethodPrice;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShippingMethodPriceBasicStruct $shippingMethodPrice): void
    {
        parent::doRemoveByKey($this->getKey($shippingMethodPrice));
    }

    public function exists(ShippingMethodPriceBasicStruct $shippingMethodPrice): bool
    {
        return parent::has($this->getKey($shippingMethodPrice));
    }

    public function getList(array $uuids): ShippingMethodPriceBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShippingMethodPriceBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getUuid();
        });
    }

    public function getShippingMethodUuids(): array
    {
        return $this->fmap(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodUuid();
        });
    }

    public function filterByShippingMethodUuid(string $uuid): ShippingMethodPriceBasicCollection
    {
        return $this->filter(function (ShippingMethodPriceBasicStruct $shippingMethodPrice) use ($uuid) {
            return $shippingMethodPrice->getShippingMethodUuid() === $uuid;
        });
    }

    protected function getKey(ShippingMethodPriceBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
