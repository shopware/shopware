<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryStateTranslation;

use Shopware\System\Country\Collection\CountryStateTranslationDetailCollection;
use Shopware\System\Country\Event\CountryState\CountryStateBasicLoadedEvent;
use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CountryStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryStateTranslationDetailCollection
     */
    protected $countryStateTranslations;

    public function __construct(CountryStateTranslationDetailCollection $countryStateTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
