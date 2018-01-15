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

    public function get(string $id): ? CustomerBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerBasicStruct
    {
        return parent::current();
    }

    public function getGroupIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getGroupId() === $id;
        });
    }

    public function getDefaultPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultPaymentMethodId();
        });
    }

    public function filterByDefaultPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getDefaultPaymentMethodId() === $id;
        });
    }

    public function getShopIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getShopId();
        });
    }

    public function filterByShopId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getShopId() === $id;
        });
    }

    public function getLastPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getLastPaymentMethodId();
        });
    }

    public function filterByLastPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getLastPaymentMethodId() === $id;
        });
    }

    public function getDefaultBillingAddressIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultBillingAddressId();
        });
    }

    public function filterByDefaultBillingAddressId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getDefaultBillingAddressId() === $id;
        });
    }

    public function getDefaultShippingAddressIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getDefaultShippingAddressId();
        });
    }

    public function filterByDefaultShippingAddressId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getDefaultShippingAddressId() === $id;
        });
    }

    public function getSessionIds(): array
    {
        return $this->fmap(function (CustomerBasicStruct $customer) {
            return $customer->getSessionId();
        });
    }

    public function filterBySessionId(string $id): self
    {
        return $this->filter(function (CustomerBasicStruct $customer) use ($id) {
            return $customer->getSessionId() === $id;
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
