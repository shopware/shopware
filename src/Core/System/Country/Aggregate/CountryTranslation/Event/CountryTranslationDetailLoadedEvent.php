<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationDetailCollection;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class CountryTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var CountryTranslationDetailCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationDetailCollection $countryTranslations, Context $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCountryTranslations(): CountryTranslationDetailCollection
    {
        return $this->countryTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryTranslations->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->countryTranslations->getCountries(), $this->context);
        }
        if ($this->countryTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->countryTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
