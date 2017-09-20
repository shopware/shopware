<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerAddressBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customerAddress.basic.loaded';

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $customerAddresses;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerAddressBasicCollection $customerAddresses, TranslationContext $context)
    {
        $this->customerAddresses = $customerAddresses;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomerAddresses(): CustomerAddressBasicCollection
    {
        return $this->customerAddresses;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new AreaCountryBasicLoadedEvent($this->customerAddresses->getCountries(), $this->context),
            new AreaCountryStateBasicLoadedEvent($this->customerAddresses->getStates(), $this->context),
        ]);
    }
}
