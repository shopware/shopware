<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class CustomerCollection extends EntityCollection
{
    /**
     * @var CustomerStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CustomerStruct
    {
        return parent::get($id);
    }

    public function current(): CustomerStruct
    {
        return parent::current();
    }

    public function getGroupIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getGroupId() === $id;
        });
    }

    public function getDefaultPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getDefaultPaymentMethodId();
        });
    }

    public function filterByDefaultPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getDefaultPaymentMethodId() === $id;
        });
    }

    public function getSalesChannelIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getSalesChannelId() === $id;
        });
    }

    public function getLastPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getLastPaymentMethodId();
        });
    }

    public function filterByLastPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getLastPaymentMethodId() === $id;
        });
    }

    public function getDefaultBillingAddressIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getDefaultBillingAddressId();
        });
    }

    public function filterByDefaultBillingAddressId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getDefaultBillingAddressId() === $id;
        });
    }

    public function getDefaultShippingAddressIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getDefaultShippingAddressId();
        });
    }

    public function filterByDefaultShippingAddressId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getDefaultShippingAddressId() === $id;
        });
    }

    public function getSessionIds(): array
    {
        return $this->fmap(function (CustomerStruct $customer) {
            return $customer->getSessionId();
        });
    }

    public function filterBySessionId(string $id): self
    {
        return $this->filter(function (CustomerStruct $customer) use ($id) {
            return $customer->getSessionId() === $id;
        });
    }

    public function getGroups(): CustomerGroupCollection
    {
        return new CustomerGroupCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getGroup();
            })
        );
    }

    public function getDefaultPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getDefaultPaymentMethod();
            })
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getSalesChannel();
            })
        );
    }

    public function getLastPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getLastPaymentMethod();
            })
        );
    }

    public function getDefaultBillingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getDefaultBillingAddress();
            })
        );
    }

    public function getDefaultShippingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(function (CustomerStruct $customer) {
                return $customer->getDefaultShippingAddress();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CustomerStruct::class;
    }
}
