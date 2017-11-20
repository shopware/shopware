<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryAreaTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryAreaTranslationDetailCollection;
use Shopware\Country\Event\CountryArea\CountryAreaBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CountryAreaTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'country_area_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryAreaTranslationDetailCollection
     */
    protected $countryAreaTranslations;

    public function __construct(CountryAreaTranslationDetailCollection $countryAreaTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationDetailCollection
    {
        return $this->countryAreaTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryAreaTranslations->getCountryAreas()->count() > 0) {
            $events[] = new CountryAreaBasicLoadedEvent($this->countryAreaTranslations->getCountryAreas(), $this->context);
        }
        if ($this->countryAreaTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->countryAreaTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
