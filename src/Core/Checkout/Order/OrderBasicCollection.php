<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\System\Touchpoint\TouchpointBasicCollection;
use Shopware\Core\Checkout\Customer\CustomerBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateBasicCollection;
use Shopware\Core\Checkout\Order\OrderBasicStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodBasicCollection;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Currency\CurrencyBasicCollection;

class OrderBasicCollection extends EntityCollection
{
    /**
     * @var OrderBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderBasicStruct
    {
        return parent::current();
    }

    public function getCustomerIds(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getCustomerId();
        });
    }

    public function filterByCustomerId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getCustomerId() === $id;
        });
    }

    public function getStateIds(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getStateId();
        });
    }

    public function filterByStateId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getStateId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getPaymentMethodId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getCurrencyId() === $id;
        });
    }

    public function getTouchpointIs(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getTouchpointId();
        });
    }

    public function filterByTouchpointId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getTouchpointId() === $id;
        });
    }

    public function getBillingAddressIds(): array
    {
        return $this->fmap(function (OrderBasicStruct $order) {
            return $order->getBillingAddressId();
        });
    }

    public function filterByBillingAddressId(string $id): self
    {
        return $this->filter(function (OrderBasicStruct $order) use ($id) {
            return $order->getBillingAddressId() === $id;
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

    public function getStates(): \Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateBasicCollection
    {
        return new \Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateBasicCollection(
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

    public function getTouchpoints(): TouchpointBasicCollection
    {
        return new TouchpointBasicCollection(
            $this->fmap(function (OrderBasicStruct $order) {
                return $order->getTouchpoint();
            })
        );
    }

    public function getBillingAddress(): \Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressBasicCollection
    {
        return new \Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressBasicCollection(
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
