<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateDetailCollection;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Event\CountryStateTranslationBasicLoadedEvent;
use Shopware\Core\System\Country\Event\CountryBasicLoadedEvent;

class CountryStateDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'country_state.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateDetailCollection
     */
    protected $countryStates;

    public function __construct(CountryStateDetailCollection $countryStates, Context $context)
    {
        $this->context = $context;
        $this->countryStates = $countryStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
