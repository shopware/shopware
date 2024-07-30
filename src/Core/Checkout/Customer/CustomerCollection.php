<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<CustomerEntity>
 */
#[Package('checkout')]
class CustomerCollection extends EntityCollection
{
    /**
     * @return array<string>
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
     * @deprecated tag:v6.7.0 - will be removed
     *
     * @return array<string>
     */
    public function getDefaultPaymentMethodIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method of a customer will be removed.');

        return $this->fmap(fn (CustomerEntity $customer) => $customer->getDefaultPaymentMethodId());
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    public function filterByDefaultPaymentMethodId(string $id): self
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method of a customer will be removed.');

        return $this->filter(fn (CustomerEntity $customer) => $customer->getDefaultPaymentMethodId() === $id);
    }

    /**
     * @return array<string>
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
     * @return array<string>
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
     * @return array<string>
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
     * @return array<string>
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

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    public function getDefaultPaymentMethods(): PaymentMethodCollection
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The default payment method of a customer will be removed.');

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
     * @return array<string>
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
