<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryAreaTranslation;

use Shopware\Api\Country\Collection\CountryAreaTranslationDetailCollection;
use Shopware\Api\Country\Event\CountryArea\CountryAreaBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

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
