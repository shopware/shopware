<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateDetailCollection;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Event\CountryStateTranslationBasicLoadedEvent;
use Shopware\System\Country\Event\CountryBasicLoadedEvent;

class CountryStateDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateDetailCollection
     */
    protected $countryStates;

    public function __construct(CountryStateDetailCollection $countryStates, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountryStates(): CountryStateDetailCollection
    {
        return $this->countryStates;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->countryStates->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->countryStates->getCountries(), $this->context);
        }
        if ($this->countryStates->getTranslations()->count() > 0) {
            $events[] = new CountryStateTranslationBasicLoadedEvent($this->countryStates->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
