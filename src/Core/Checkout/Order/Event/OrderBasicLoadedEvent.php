<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Core\System\Touchpoint\Event\TouchpointBasicLoadedEvent;

class OrderBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    public function __construct(OrderBasicCollection $orders, Context $context)
    {
        $this->context = $context;
        $this->orders = $orders;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->orders->getTouchpoints()->count() > 0) {
            $events[] = new TouchpointBasicLoadedEvent($this->orders->getTouchpoints(), $this->context);
        }
        if ($this->orders->getBillingAddress()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orders->getBillingAddress(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
