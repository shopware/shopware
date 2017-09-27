<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Struct\OrderBasicCollection;
use Shopware\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Shop\Event\ShopBasicLoadedEvent;

class OrderBasicLoadedEvent extends NestedEvent
{
    const NAME = 'order.basic.loaded';

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderBasicCollection $orders, TranslationContext $context)
    {
        $this->orders = $orders;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
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
        if ($this->orders->getBillingAddresses()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orders->getBillingAddresses(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
