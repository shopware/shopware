<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<CustomerEntity>
 */
class CustomerCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getGroupIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getGroupId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getDefaultPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getDefaultPaymentMethodId();
        });
    }

    public function filterByDefaultPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getDefaultPaymentMethodId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getSalesChannelIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getSalesChannelId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLastPaymentMethodIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getLastPaymentMethodId();
        });
    }

    public function filterByLastPaymentMethodId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getLastPaymentMethodId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getDefaultBillingAddressIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getDefaultBillingAddressId();
        });
    }

    public function filterByDefaultBillingAddressId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getDefaultBillingAddressId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getDefaultShippingAddressIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getDefaultShippingAddressId();
        });
    }

    public function filterByDefaultShippingAddressId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return $customer->getDefaultShippingAddressId() === $id;
        });
    }

    public function getGroups(): CustomerGroupCollection
    {
        return new CustomerGroupCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getGroup();
            })
        );
    }

    public function getDefaultPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getDefaultPaymentMethod();
            })
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getSalesChannel();
            })
        );
    }

    public function getLastPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getLastPaymentMethod();
            })
        );
    }

    public function getDefaultBillingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getDefaultBillingAddress();
            })
        );
    }

    public function getDefaultShippingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(function (CustomerEntity $customer) {
                return $customer->getDefaultShippingAddress();
            })
        );
    }

    /**
     * @return list<string>
     */
    public function getListVatIds(): array
    {
        return $this->fmap(function (CustomerEntity $customer) {
            return $customer->getVatIds();
        });
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(function (CustomerEntity $customer) use ($id) {
            return \in_array($id, $customer->getVatIds() ?? [], true);
        });
    }

    public function getApiAlias(): string
    {
        return 'customer_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerEntity::class;
    }
}
