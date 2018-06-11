<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationDetailCollection;
use Shopware\Core\System\Country\Event\CountryBasicLoadedEvent;

class CountryTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
