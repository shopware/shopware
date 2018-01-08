<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderBasicStruct;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class OrderBasicCollection extends EntityCollection
{
    /**
     * @var OrderBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? OrderBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): OrderBasicStruct
    {
        return parent::current();
    }

    public function getCustomerUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getCustomerUuid();
        });
    }

    public function filterByCustomerUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getCustomerUuid() === $uuid;
        });
    }

    public function getStateUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getStateUuid();
        });
    }

    public function filterByStateUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getStateUuid() === $uuid;
        });
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getPaymentMethodUuid();
        });
    }

    public function filterByPaymentMethodUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getPaymentMethodUuid() === $uuid;
        });
    }

    public function getCurrencyUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getCurrencyUuid();
        });
    }

    public function filterByCurrencyUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getCurrencyUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getShopUuid() === $uuid;
        });
    }

    public function getBillingAddressUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getBillingAddressUuid();
        });
    }

    public function filterByBillingAddressUuid(string $uuid): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($uuid) {
            return $order->getBillingAddressUuid() === $uuid;
        });
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return new CustomerBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getCustomer();
            })
        );
    }

    public function getStates(): OrderStateBasicCollection
    {
        return new OrderStateBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getState();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getPaymentMethod();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getCurrency();
            })
        );
    }

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getShop();
            })
        );
    }

    public function getBillingAddress(): OrderAddressBasicCollection
    {
        return new OrderAddressBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getBillingAddress();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderBasicStruct::class;
    }
}
