<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRule;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryArea\CountryAreaBasicLoadedEvent;
use Shopware\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Tax\Collection\TaxAreaRuleDetailCollection;
use Shopware\Tax\Event\Tax\TaxBasicLoadedEvent;
use Shopware\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationBasicLoadedEvent;

class TaxAreaRuleDetailLoadedEvent extends NestedEvent
{
    const NAME = 'tax_area_rule.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleDetailCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleDetailCollection $taxAreaRules, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getTaxAreaRules(): TaxAreaRuleDetailCollection
    {
        return $this->taxAreaRules;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->taxAreaRules->getCountryAreas()->count() > 0) {
            $events[] = new CountryAreaBasicLoadedEvent($this->taxAreaRules->getCountryAreas(), $this->context);
        }
        if ($this->taxAreaRules->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->taxAreaRules->getCountries(), $this->context);
        }
        if ($this->taxAreaRules->getCountryStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->taxAreaRules->getCountryStates(), $this->context);
        }
        if ($this->taxAreaRules->getTaxes()->count() > 0) {
            $events[] = new TaxBasicLoadedEvent($this->taxAreaRules->getTaxes(), $this->context);
        }
        if ($this->taxAreaRules->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->taxAreaRules->getCustomerGroups(), $this->context);
        }
        if ($this->taxAreaRules->getTranslations()->count() > 0) {
            $events[] = new TaxAreaRuleTranslationBasicLoadedEvent($this->taxAreaRules->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
