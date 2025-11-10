<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
abstract class NestedEvent extends Event implements ShopwareEvent
{
    use JsonSerializableTrait;

    public function getEvents(): ?NestedEventCollection
    {
        return null;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public function getFlatEventList(): NestedEventCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );
        $events = [$this];

        if (!$nestedEvents = $this->getEvents()) {
            return new NestedEventCollection($events);
        }

        foreach ($nestedEvents as $event) {
            $events[] = $event;
            foreach ($event->getFlatEventList() as $item) {
                $events[] = $item;
            }
        }

        return new NestedEventCollection($events);
    }
}
