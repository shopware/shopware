<?php declare(strict_types=1);

namespace Shopware\Area\Event;

use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaDetailLoadedEvent extends NestedEvent
{
    const NAME = 'area.detail.loaded';

    /**
     * @var AreaDetailCollection
     */
    protected $areas;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaDetailCollection $areas, TranslationContext $context)
    {
        $this->areas = $areas;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreas(): AreaDetailCollection
    {
        return $this->areas;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new AreaBasicLoadedEvent($this->areas, $this->context),
            new AreaCountryBasicLoadedEvent($this->areas->getCountries(), $this->context),
        ]);
    }
}
