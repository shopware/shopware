<?php declare(strict_types=1);

namespace Shopware\Customer\Event\Customer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerBasicCollection;
use Shopware\Customer\Event\CustomerAddress\CustomerAddressBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CustomerBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

    public function __construct(CustomerBasicCollection $customers, TranslationContext $context)
    {
        $this->context = $context;
        $this->customers = $customers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customers->getGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->customers->getGroups(), $this->context);
        }
        if ($this->customers->getDefaultPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->customers->getDefaultPaymentMethods(), $this->context);
        }
        if ($this->customers->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->customers->getShops(), $this->context);
        }
        if ($this->customers->getLastPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->customers->getLastPaymentMethods(), $this->context);
        }
        if ($this->customers->getDefaultBillingAddress()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->customers->getDefaultBillingAddress(), $this->context);
        }
        if ($this->customers->getDefaultShippingAddress()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->customers->getDefaultShippingAddress(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
