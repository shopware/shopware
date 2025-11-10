<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @template TEvent of NestedEvent = NestedEvent
 *
 * @extends Collection<TEvent>
 */
#[Package('framework')]
class NestedEventCollection extends Collection
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major as it is unused
     */
    public function getFlatEventList(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'),
        );
        $events = [];

        foreach ($this->getElements() as $event) {
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

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return string
     *
     * @return TEvent
     *
     * @phpstan-ignore return.phpDocType (Does not work as expected. See https://github.com/phpstan/phpstan/discussions/13728)
     */
    protected function getExpectedClass(): ?string
    {
        return NestedEvent::class;
    }
}
