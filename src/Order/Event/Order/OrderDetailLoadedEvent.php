<?php declare(strict_types=1);

namespace Shopware\Order\Event\Order;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Collection\OrderDetailCollection;
use Shopware\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Order\Event\OrderDelivery\OrderDeliveryBasicLoadedEvent;
use Shopware\Order\Event\OrderLineItem\OrderLineItemBasicLoadedEvent;
use Shopware\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class OrderDetailLoadedEvent extends NestedEvent
{
    const NAME = 'order.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderDetailCollection
     */
    protected $orders;

    public function __construct(OrderDetailCollection $orders, TranslationContext $context)
    {
        $this->context = $context;
        $this->orders = $orders;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getOrders(): OrderDetailCollection
    {
        return $this->orders;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orders->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->orders->getCustomers(), $this->context);
        }
        if ($this->orders->getStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orders->getStates(), $this->context);
        }
        if ($this->orders->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->orders->getPaymentMethods(), $this->context);
        }
        if ($this->orders->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->orders->getCurrencies(), $this->context);
        }
        if ($this->orders->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->orders->getShops(), $this->context);
        }
        if ($this->orders->getBillingAddress()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orders->getBillingAddress(), $this->context);
        }
        if ($this->orders->getDeliveries()->count() > 0) {
            $events[] = new OrderDeliveryBasicLoadedEvent($this->orders->getDeliveries(), $this->context);
        }
        if ($this->orders->getLineItems()->count() > 0) {
            $events[] = new OrderLineItemBasicLoadedEvent($this->orders->getLineItems(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
