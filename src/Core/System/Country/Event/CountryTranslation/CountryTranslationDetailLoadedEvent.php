<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryTranslation;

use Shopware\System\Country\Collection\CountryTranslationDetailCollection;
use Shopware\System\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryTranslationDetailCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationDetailCollection $countryTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
