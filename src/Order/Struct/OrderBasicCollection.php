<?php declare(strict_types=1);

namespace Shopware\Order\Struct;

use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Framework\Struct\Collection;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\OrderState\Struct\OrderStateBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\Shop\Struct\ShopBasicCollection;

class OrderBasicCollection extends Collection
{
    /**
     * @var OrderBasicStruct[]
     */
    protected $elements = [];

    public function add(OrderBasicStruct $order): void
    {
        $key = $this->getKey($order);
        $this->elements[$key] = $order;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(OrderBasicStruct $order): void
    {
        parent::doRemoveByKey($this->getKey($order));
    }

    public function exists(OrderBasicStruct $order): bool
    {
        return parent::has($this->getKey($order));
    }

    public function getList(array $uuids): OrderBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? OrderBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getUuid();
        });
    }

    public function merge(OrderBasicCollection $collection)
    {
        /** @var OrderBasicStruct $order */
        foreach ($collection as $order) {
            if ($this->has($this->getKey($order))) {
                continue;
            }
            $this->add($order);
        }
    }

    public function getCustomerUuids(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getCustomerUuid();
        });
    }

    public function filterByCustomerUuid(string $uuid): OrderBasicCollection
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

    public function filterByStateUuid(string $uuid): OrderBasicCollection
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

    public function filterByPaymentMethodUuid(string $uuid): OrderBasicCollection
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

    public function filterByCurrencyUuid(string $uuid): OrderBasicCollection
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

    public function filterByShopUuid(string $uuid): OrderBasicCollection
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

    public function filterByBillingAddressUuid(string $uuid): OrderBasicCollection
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

    public function getBillingAddresses(): OrderAddressBasicCollection
    {
        return new OrderAddressBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getBillingAddress();
            })
        );
    }

    protected function getKey(OrderBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
