<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\Country;

use Shopware\Api\Country\Collection\CountryDetailCollection;
use Shopware\Api\Country\Event\CountryArea\CountryAreaBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryTranslation\CountryTranslationBasicLoadedEvent;
use Shopware\Api\Customer\Event\CustomerAddress\CustomerAddressBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderAddress\OrderAddressBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryDetailCollection
     */
    protected $countries;

    public function __construct(CountryDetailCollection $countries, TranslationContext $context)
    {
        $this->context = $context;
        $this->countries = $countries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountries(): CountryDetailCollection
    {
        return $this->countries;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countries->getAreas()->count() > 0) {
            $events[] = new CountryAreaBasicLoadedEvent($this->countries->getAreas(), $this->context);
        }
        if ($this->countries->getStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->countries->getStates(), $this->context);
        }
        if ($this->countries->getTranslations()->count() > 0) {
            $events[] = new CountryTranslationBasicLoadedEvent($this->countries->getTranslations(), $this->context);
        }
        if ($this->countries->getCustomerAddresses()->count() > 0) {
            $events[] = new CustomerAddressBasicLoadedEvent($this->countries->getCustomerAddresses(), $this->context);
        }
        if ($this->countries->getOrderAddresses()->count() > 0) {
            $events[] = new OrderAddressBasicLoadedEvent($this->countries->getOrderAddresses(), $this->context);
        }
        if ($this->countries->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->countries->getShops(), $this->context);
        }
        if ($this->countries->getTaxAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->countries->getTaxAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
