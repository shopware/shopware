<?php declare(strict_types=1);

namespace Shopware\Area\Event;

use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaBasicLoadedEvent extends NestedEvent
{
    const NAME = 'area.basic.loaded';

    /**
     * @var AreaBasicCollection
     */
    protected $areas;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(AreaBasicCollection $areas, TranslationContext $context)
    {
        $this->areas = $areas;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getAreas(): AreaBasicCollection
    {
        return $this->areas;
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
