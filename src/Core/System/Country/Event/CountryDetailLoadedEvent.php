<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryArea\Event\CountryAreaBasicLoadedEvent;
use Shopware\Core\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\Event\CountryTranslationBasicLoadedEvent;
use Shopware\Core\System\Country\Collection\CountryDetailCollection;

class CountryDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CountryDetailCollection
     */
    protected $countries;

    public function __construct(CountryDetailCollection $countries, Context $context)
    {
        $this->context = $context;
        $this->countries = $countries;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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

        return new NestedEventCollection($events);
    }
}
