<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void             add(NestedEvent $entity)
 * @method void             set(string $key, NestedEvent $entity)
 * @method NestedEvent[]    getIterator()
 * @method NestedEvent[]    getElements()
 * @method NestedEvent|null get(string $key)
 * @method NestedEvent|null first()
 * @method NestedEvent|null last()
 */
class NestedEventCollection extends Collection
{
    public function getFlatEventList(): self
    {
        $events = [];

        foreach ($this->getIterator() as $event) {
            foreach ($event->getFlatEventList() as $item) {
                $events[] = $item;
            }
        }

        return new self($events);
    }

    public function getApiAlias(): string
    {
        return 'dal_nested_event_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return NestedEvent::class;
    }
}
