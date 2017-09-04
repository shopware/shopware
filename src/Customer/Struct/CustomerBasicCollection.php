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

namespace Shopware\Customer\Struct;

use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Framework\Struct\Collection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;

class CustomerBasicCollection extends Collection
{
    /**
     * @var CustomerBasicStruct[]
     */
    protected $elements = [];

    public function add(CustomerBasicStruct $customer): void
    {
        $key = $this->getKey($customer);
        $this->elements[$key] = $customer;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CustomerBasicStruct $customer): void
    {
        parent::doRemoveByKey($this->getKey($customer));
    }

    public function exists(CustomerBasicStruct $customer): bool
    {
        return parent::has($this->getKey($customer));
    }

    public function getList(array $uuids): CustomerBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CustomerBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getUuid();
        });
    }

    public function getLastPaymentMethodUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getLastPaymentMethodUuid();
        });
    }

    public function filterByLastPaymentMethodUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getLastPaymentMethodUuid() === $uuid;
        });
    }

    public function getGroupUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getGroupUuid();
        });
    }

    public function filterByGroupUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getGroupUuid() === $uuid;
        });
    }

    public function getDefaultPaymentMethodUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultPaymentMethodUuid();
        });
    }

    public function filterByDefaultPaymentMethodUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getDefaultPaymentMethodUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getShopUuid() === $uuid;
        });
    }

    public function getMainShopUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getMainShopUuid();
        });
    }

    public function filterByMainShopUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getMainShopUuid() === $uuid;
        });
    }

    public function getPriceGroupUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getPriceGroupUuid();
        });
    }

    public function filterByPriceGroupUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getPriceGroupUuid() === $uuid;
        });
    }

    public function getDefaultBillingAddressUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultBillingAddressUuid();
        });
    }

    public function filterByDefaultBillingAddressUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getDefaultBillingAddressUuid() === $uuid;
        });
    }

    public function getDefaultShippingAddressUuids(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultShippingAddressUuid();
        });
    }

    public function filterByDefaultShippingAddressUuid(string $uuid): CustomerBasicCollection
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($uuid) {
            return $customer->getDefaultShippingAddressUuid() === $uuid;
        });
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getCustomerGroup();
            })
        );
    }

    public function getDefaultShippingAddresss(): CustomerAddressBasicCollection
    {
        return new CustomerAddressBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getDefaultShippingAddress();
            })
        );
    }

    public function getDefaultBillingAddresss(): CustomerAddressBasicCollection
    {
        return new CustomerAddressBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getDefaultBillingAddress();
            })
        );
    }

    public function getLastPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getLastPaymentMethod();
            })
        );
    }

    public function getDefaultPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getDefaultPaymentMethod();
            })
        );
    }

    public function getAddressUuids(): array
    {
        return array_merge(
            array_values($this->getDefaultBillingAddressUuids()),
            array_values($this->getDefaultShippingAddressUuids())
        );
    }

    public function getPaymentMethodUuids(): array
    {
        return array_merge(
            array_values($this->getDefaultPaymentMethodUuids()),
            array_values($this->getLastPaymentMethodUuids())
        );
    }

    protected function getKey(CustomerBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
