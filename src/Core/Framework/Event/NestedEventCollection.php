<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Struct\Collection;

class NestedEventCollection extends Collection
{
    /**
     * @var NestedEvent[]
     */
    protected $elements = [];

    public function add(NestedEvent $event): void
    {
        $this->elements[] = $event;
    }

    public function getFlatEventList(): self
    {
        $events = [];

        foreach ($this->elements as $event) {
            foreach ($event->getFlatEventList() as $item) {
                $events[] = $item;
            }
        }

        return new self($events);
    }
}
