<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryArea;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryAreaDetailCollection;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;

class CountryAreaDetailLoadedEvent extends NestedEvent
{
    const NAME = 'country_area.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryAreaDetailCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaDetailCollection $countryAreas, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryAreas(): CountryAreaDetailCollection
    {
        return $this->countryAreas;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryAreas->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->countryAreas->getCountries(), $this->context);
        }
        if ($this->countryAreas->getTranslations()->count() > 0) {
            $events[] = new CountryAreaTranslationBasicLoadedEvent($this->countryAreas->getTranslations(), $this->context);
        }
        if ($this->countryAreas->getTaxAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->countryAreas->getTaxAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
