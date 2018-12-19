<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Struct\Collection;

class NestedEventCollection extends Collection
{
    public function getFlatEventList(): self
    {
        $events = [];

        /** @var NestedEvent $event */
        foreach ($this->elements as $event) {
            foreach ($event->getFlatEventList() as $item) {
                $events[] = $item;
            }
        }

        return new self($events);
    }

    protected function getExpectedClass(): ?string
    {
        return NestedEvent::class;
    }
}
