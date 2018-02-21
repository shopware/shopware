<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryArea;

use Shopware\Api\Country\Collection\CountryAreaDetailCollection;
use Shopware\Api\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Country\Event\CountryAreaTranslation\CountryAreaTranslationBasicLoadedEvent;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryAreaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CountryAreaDetailCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaDetailCollection $countryAreas, ShopContext $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
