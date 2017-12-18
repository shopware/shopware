<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryTranslation;

use Shopware\Api\Country\Collection\CountryTranslationDetailCollection;
use Shopware\Api\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryTranslationDetailCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationDetailCollection $countryTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->countryTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
