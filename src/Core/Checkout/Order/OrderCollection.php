<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @method void             add(OrderEntity $entity)
 * @method void             set(string $key, OrderEntity $entity)
 * @method OrderEntity[]    getIterator()
 * @method OrderEntity[]    getElements()
 * @method OrderEntity|null get(string $key)
 * @method OrderEntity|null first()
 * @method OrderEntity|null last()
 */
class OrderCollection extends EntityCollection
{
    public function getCurrencyIds(): array
    {
        return $this->fmap(function (OrderEntity $order) {
            return $order->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (OrderEntity $order) use ($id) {
            return $order->getCurrencyId() === $id;
        });
    }

    public function getSalesChannelIs(): array
    {
        return $this->fmap(function (OrderEntity $order) {
            return $order->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (OrderEntity $order) use ($id) {
            return $order->getSalesChannelId() === $id;
        });
    }

    public function getOrderCustomers(): CustomerCollection
    {
        return new CustomerCollection(
            $this->fmap(function (OrderEntity $order) {
                return $order->getOrderCustomer();
            })
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(function (OrderEntity $order) {
                return $order->getCurrency();
            })
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (OrderEntity $order) {
                return $order->getSalesChannel();
            })
        );
    }

    public function getBillingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(function (OrderEntity $order) {
                return $order->getAddresses();
            })
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
