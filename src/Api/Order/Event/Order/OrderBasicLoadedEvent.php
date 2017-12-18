<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\Order;

use Shopware\Api\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Api\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    public function __construct(OrderBasicCollection $orders, TranslationContext $context)
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

    public function getOrders(): OrderBasicCollection
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

        return new NestedEventCollection($events);
    }
}
