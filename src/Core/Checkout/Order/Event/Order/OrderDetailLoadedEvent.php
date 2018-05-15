<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\Order;

use Shopware\Application\Application\Event\Application\ApplicationBasicLoadedEvent;
use Shopware\System\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Checkout\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Checkout\Order\Collection\OrderDetailCollection;
use Shopware\Checkout\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderDelivery\OrderDeliveryBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderLineItem\OrderLineItemBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderTransaction\OrderTransactionBasicLoadedEvent;
use Shopware\Checkout\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderDetailCollection
     */
    protected $orders;

    public function __construct(OrderDetailCollection $orders, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orders = $orders;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
        if ($this->orders->getApplications()->count() > 0) {
            $events[] = new ApplicationBasicLoadedEvent($this->orders->getApplications(), $this->context);
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
        if ($this->orders->getTransactions()->count() > 0) {
            $events[] = new OrderTransactionBasicLoadedEvent($this->orders->getTransactions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
