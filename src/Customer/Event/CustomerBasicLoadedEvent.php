<?php declare(strict_types=1);

namespace Shopware\Customer\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;

class CustomerBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customer.basic.loaded';

    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerBasicCollection $customers, TranslationContext $context)
    {
        $this->customers = $customers;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customers->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->customers->getCustomerGroups(), $this->context);
        }
        if ($this->customers->getDefaultShippingAddresss()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->customers->getDefaultShippingAddresss(), $this->context);
        }
        if ($this->customers->getDefaultBillingAddresss()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->customers->getDefaultBillingAddresss(), $this->context);
        }
        if ($this->customers->getLastPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->customers->getLastPaymentMethods(), $this->context);
        }
        if ($this->customers->getDefaultPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->customers->getDefaultPaymentMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
