<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Event;

use Shopware\System\Country\Event\CountryBasicLoadedEvent;
use Shopware\System\Country\Aggregate\CountryArea\Event\CountryAreaBasicLoadedEvent;
use Shopware\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Checkout\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleDetailCollection;
use Shopware\System\Tax\Event\TaxBasicLoadedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event\TaxAreaRuleTranslationBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxAreaRuleDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleDetailCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleDetailCollection $taxAreaRules, ApplicationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
