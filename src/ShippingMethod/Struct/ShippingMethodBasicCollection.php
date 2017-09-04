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

namespace Shopware\ShippingMethod\Struct;

use Shopware\Framework\Struct\Collection;

class ShippingMethodBasicCollection extends Collection
{
    /**
     * @var ShippingMethodBasicStruct[]
     */
    protected $elements = [];

    public function add(ShippingMethodBasicStruct $shippingMethod): void
    {
        $key = $this->getKey($shippingMethod);
        $this->elements[$key] = $shippingMethod;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShippingMethodBasicStruct $shippingMethod): void
    {
        parent::doRemoveByKey($this->getKey($shippingMethod));
    }

    public function exists(ShippingMethodBasicStruct $shippingMethod): bool
    {
        return parent::has($this->getKey($shippingMethod));
    }

    public function getList(array $uuids): ShippingMethodBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShippingMethodBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getUuid();
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ShippingMethodBasicCollection
    {
        return $this->filter(function (ShippingMethodBasicStruct $shippingMethod) use ($uuid) {
            return $shippingMethod->getShopUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ShippingMethodBasicStruct $shippingMethod) {
            return $shippingMethod->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ShippingMethodBasicCollection
    {
        return $this->filter(function (ShippingMethodBasicStruct $shippingMethod) use ($uuid) {
            return $shippingMethod->getCustomerGroupUuid() === $uuid;
        });
    }

    protected function getKey(ShippingMethodBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
