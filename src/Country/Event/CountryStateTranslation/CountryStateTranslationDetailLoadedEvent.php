<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryStateTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Collection\CountryStateTranslationDetailCollection;
use Shopware\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class CountryStateTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'country_state_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryStateTranslationDetailCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationDetailCollection $countryStateTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryStateTranslations(): CountryStateTranslationDetailCollection
    {
        return $this->countryStateTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryStateTranslations->getCountryStates()->count() > 0) {
            $events[] = new CountryStateBasicLoadedEvent($this->countryStateTranslations->getCountryStates(), $this->context);
        }
        if ($this->countryStateTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->countryStateTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
