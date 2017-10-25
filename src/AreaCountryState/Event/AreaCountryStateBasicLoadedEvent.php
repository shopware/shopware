<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Event;

use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryStateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'area_country_state.basic.loaded';

    /**
     * @var AreaCountryStateBasicCollection
     */
    protected $areaCountryStates;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaCountryStateBasicCollection $areaCountryStates, TranslationContext $context)
    {
        $this->areaCountryStates = $areaCountryStates;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreaCountryStates(): AreaCountryStateBasicCollection
    {
        return $this->areaCountryStates;
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
