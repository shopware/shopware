<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryState;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryStateDetailCollection;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryStateTranslation\CountryStateTranslationBasicLoadedEvent;
use Shopware\Customer\Event\CustomerAddress\CustomerAddressBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;

class CountryStateDetailLoadedEvent extends NestedEvent
{
    const NAME = 'country_state.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryStateDetailCollection
     */
    protected $countryStates;

    public function __construct(CountryStateDetailCollection $countryStates, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryStates(): CountryStateDetailCollection
    {
        return $this->countryStates;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryStates->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->countryStates->getCountries(), $this->context);
        }
        if ($this->countryStates->getTranslations()->count() > 0) {
            $events[] = new CountryStateTranslationBasicLoadedEvent($this->countryStates->getTranslations(), $this->context);
        }
        if ($this->countryStates->getCustomerAddresses()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->countryStates->getCustomerAddresses(), $this->context);
        }
        if ($this->countryStates->getOrderAddresses()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->countryStates->getOrderAddresses(), $this->context);
        }
        if ($this->countryStates->getTaxAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->countryStates->getTaxAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
