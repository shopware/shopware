<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaDetailCollection;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationBasicLoadedEvent;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class CountryAreaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var CountryAreaDetailCollection
     */
    protected $countryAreas;

    public function __construct(CountryAreaDetailCollection $countryAreas, Context $context)
    {
        $this->context = $context;
        $this->countryAreas = $countryAreas;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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

        return new NestedEventCollection($events);
    }
}
