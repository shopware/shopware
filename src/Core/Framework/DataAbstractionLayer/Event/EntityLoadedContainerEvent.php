<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class EntityLoadedContainerEvent extends NestedEvent
{
    private Context $context;

    private array $events;

    public function __construct(Context $context, array $events)
    {
        $this->context = $context;
        $this->events = $events;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection($this->events);
    }
}
