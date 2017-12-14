<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Collection;

use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class CustomerBasicCollection extends EntityCollection
{
    /**
     * @var CustomerBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CustomerBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CustomerBasicStruct
    {
        return parent::current();
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

    public function getGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getGroup();
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

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getShop();
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

    public function getDefaultBillingAddress(): CustomerAddressBasicCollection
    {
        return new CustomerAddressBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getDefaultBillingAddress();
            })
        );
    }

    public function getDefaultShippingAddress(): CustomerAddressBasicCollection
    {
        return new CustomerAddressBasicCollection(
            $this->fmap(function (CustomerBasicStruct $customer) {
                return $customer->getDefaultShippingAddress();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerBasicStruct::class;
    }
}
