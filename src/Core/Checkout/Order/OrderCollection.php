<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<OrderEntity>
 */
#[Package('customer-order')]
class OrderCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getCurrencyIds(): array
    {
        return $this->fmap(fn (OrderEntity $order) => $order->getCurrencyId());
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(fn (OrderEntity $order) => $order->getCurrencyId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getSalesChannelIs(): array
    {
        return $this->fmap(fn (OrderEntity $order) => $order->getSalesChannelId());
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(fn (OrderEntity $order) => $order->getSalesChannelId() === $id);
    }

    public function getOrderCustomers(): CustomerCollection
    {
        return new CustomerCollection(
            $this->fmap(fn (OrderEntity $order) => $order->getOrderCustomer())
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(fn (OrderEntity $order) => $order->getCurrency())
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(fn (OrderEntity $order) => $order->getSalesChannel())
        );
    }

    public function getBillingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(fn (OrderEntity $order) => $order->getAddresses())
        );
    }

    public function getApiAlias(): string
    {
        return 'order_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderEntity::class;
    }
}
