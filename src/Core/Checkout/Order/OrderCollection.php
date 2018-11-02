<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class OrderCollection extends EntityCollection
{
    /**
     * @var OrderStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStruct
    {
        return parent::get($id);
    }

    public function current(): OrderStruct
    {
        return parent::current();
    }

    public function getOrderCustomerIds(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getOrderCustomerId();
        });
    }

    public function filterByOrderCustomerId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getOrderCustomerId() === $id;
        });
    }

    public function getStateIds(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getStateId();
        });
    }

    public function filterByStateId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getStateId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getPaymentMethodId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getCurrencyId() === $id;
        });
    }

    public function getSalesChannelIs(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getSalesChannelId();
        });
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getSalesChannelId() === $id;
        });
    }

    public function getBillingAddressIds(): array
    {
        return $this->fmap(function (OrderStruct $order) {
            return $order->getBillingAddressId();
        });
    }

    public function filterByBillingAddressId(string $id): self
    {
        return $this->filter(function (OrderStruct $order) use ($id) {
            return $order->getBillingAddressId() === $id;
        });
    }

    public function getOrderCustomers(): CustomerCollection
    {
        return new CustomerCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getOrderCustomer();
            })
        );
    }

    public function getStates(): OrderStateCollection
    {
        return new OrderStateCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getState();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getPaymentMethod();
            })
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getCurrency();
            })
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getSalesChannel();
            })
        );
    }

    public function getBillingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(function (OrderStruct $order) {
                return $order->getBillingAddress();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderStruct::class;
    }
}
