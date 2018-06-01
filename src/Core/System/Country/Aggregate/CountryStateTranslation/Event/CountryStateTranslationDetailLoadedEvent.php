<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\Event\CountryStateBasicLoadedEvent;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationDetailCollection;

class CountryStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationDetailCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationDetailCollection $countryStateTranslations, Context $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->countryStateTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
