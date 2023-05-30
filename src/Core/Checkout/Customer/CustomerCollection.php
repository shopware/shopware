<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<CustomerEntity>
 */
#[Package('customer-order')]
class CustomerCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getGroupIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getGroupId());
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getGroupId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getDefaultPaymentMethodIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultPaymentMethodId());
    }

    public function filterByDefaultPaymentMethodId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getDefaultPaymentMethodId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getSalesChannelIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getSalesChannelId());
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getSalesChannelId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLastPaymentMethodIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getLastPaymentMethodId());
    }

    public function filterByLastPaymentMethodId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getLastPaymentMethodId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getDefaultBillingAddressIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultBillingAddressId());
    }

    public function filterByDefaultBillingAddressId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getDefaultBillingAddressId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getDefaultShippingAddressIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultShippingAddressId());
    }

    public function filterByDefaultShippingAddressId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => $customer->getDefaultShippingAddressId() === $id);
    }

    public function getGroups(): CustomerGroupCollection
    {
        return new CustomerGroupCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getGroup())
        );
    }

    public function getDefaultPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultPaymentMethod())
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getSalesChannel())
        );
    }

    public function getLastPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getLastPaymentMethod())
        );
    }

    public function getDefaultBillingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultBillingAddress())
        );
    }

    public function getDefaultShippingAddress(): CustomerAddressCollection
    {
        return new CustomerAddressCollection(
            $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultShippingAddress())
        );
    }

    /**
     * @return list<string>
     */
    public function getListVatIds(): array
    {
        return $this->fmap(fn (CustomerEntity $customer) => $customer->getVatIds());
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(fn (CustomerEntity $customer) => \in_array($id, $customer->getVatIds() ?? [], true));
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
