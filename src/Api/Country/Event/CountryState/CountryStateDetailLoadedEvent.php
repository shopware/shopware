<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryState;

use Shopware\Api\Country\Collection\CountryStateDetailCollection;
use Shopware\Api\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryStateTranslation\CountryStateTranslationBasicLoadedEvent;
use Shopware\Api\Customer\Event\CustomerAddress\CustomerAddressBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryStateDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CountryStateDetailCollection
     */
    protected $countryStates;

    public function __construct(CountryStateDetailCollection $countryStates, ShopContext $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
