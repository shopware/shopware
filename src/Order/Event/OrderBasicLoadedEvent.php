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
        return new NestedEventCollection([
            new CustomerBasicLoadedEvent($this->orders->getCustomers(), $this->context),
            new OrderStateBasicLoadedEvent($this->orders->getStates(), $this->context),
            new PaymentMethodBasicLoadedEvent($this->orders->getPaymentMethods(), $this->context),
            new CurrencyBasicLoadedEvent($this->orders->getCurrencies(), $this->context),
            new ShopBasicLoadedEvent($this->orders->getShops(), $this->context),
            new OrderAddressBasicLoadedEvent($this->orders->getBillingAddresses(), $this->context),
        ]);
    }
}
