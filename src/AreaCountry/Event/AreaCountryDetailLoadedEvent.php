<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Event;

use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryDetailLoadedEvent extends NestedEvent
{
    const NAME = 'area_country.detail.loaded';

    /**
     * @var AreaCountryDetailCollection
     */
    protected $areaCountries;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaCountryDetailCollection $areaCountries, TranslationContext $context)
    {
        $this->areaCountries = $areaCountries;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreaCountries(): AreaCountryDetailCollection
    {
        return $this->areaCountries;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new AreaCountryBasicLoadedEvent($this->areaCountries, $this->context),
        ];

        if ($this->areaCountries->getStates()->count() > 0) {
            $events[] = new AreaCountryStateBasicLoadedEvent($this->areaCountries->getStates(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
