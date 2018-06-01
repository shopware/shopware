<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryArea\Collection\CountryAreaDetailCollection;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Event\CountryAreaTranslationBasicLoadedEvent;
use Shopware\Core\System\Country\Event\CountryBasicLoadedEvent;

class CountryAreaDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_area.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
