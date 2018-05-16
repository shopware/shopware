<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\Order;

use Shopware\Application\Application\Event\Application\ApplicationBasicLoadedEvent;
use Shopware\System\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Checkout\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Checkout\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Checkout\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    public function __construct(OrderBasicCollection $orders, ApplicationContext $context)
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
        if ($this->orders->getApplications()->count() > 0) {
            $events[] = new ApplicationBasicLoadedEvent($this->orders->getApplications(), $this->context);
        }
        if ($this->orders->getBillingAddress()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->orders->getBillingAddress(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
