<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryArea\Event\CountryAreaBasicLoadedEvent;
use Shopware\Core\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Core\System\Country\Event\CountryBasicLoadedEvent;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleDetailCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationBasicLoadedEvent;
use Shopware\Core\System\Tax\Event\TaxBasicLoadedEvent;

class TaxAreaRuleDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var TaxAreaRuleDetailCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleDetailCollection $taxAreaRules, Context $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
