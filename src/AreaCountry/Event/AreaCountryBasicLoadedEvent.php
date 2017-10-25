<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Event;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryBasicLoadedEvent extends NestedEvent
{
    const NAME = 'area_country.basic.loaded';

    /**
     * @var AreaCountryBasicCollection
     */
    protected $areaCountries;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaCountryBasicCollection $areaCountries, TranslationContext $context)
    {
        $this->areaCountries = $areaCountries;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreaCountries(): AreaCountryBasicCollection
    {
        return $this->areaCountries;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
