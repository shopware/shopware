<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerAddress\Event;

use Shopware\System\Country\Event\CountryBasicLoadedEvent;
use Shopware\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerAddressDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_address.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Customer\Aggregate\CustomerAddress\Collection\CustomerAddressDetailCollection
     */
    protected $customerAddresses;

    public function __construct(CustomerAddressDetailCollection $customerAddresses, ApplicationContext $context)
    {
        $this->context = $context;
        $this->customerAddresses = $customerAddresses;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCustomerAddresses(): CustomerAddressDetailCollection
    {
        return $this->customerAddresses;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerAddresses->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->customerAddresses->getCustomers(), $this->context);
        }
        if ($this->customerAddresses->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->customerAddresses->getCountries(), $this->context);
        }
        if ($this->customerAddresses->getCountryStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->customerAddresses->getCountryStates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
