<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryStateTranslation;

use Shopware\Api\Country\Collection\CountryStateTranslationDetailCollection;
use Shopware\Api\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.detail.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CountryStateTranslationDetailCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationDetailCollection $countryStateTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
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
