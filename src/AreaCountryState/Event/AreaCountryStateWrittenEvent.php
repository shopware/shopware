<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryStateWrittenEvent extends NestedEvent
{
    const NAME = 'area_country_state.written';

    /**
     * @var string[]
     */
    private $areaCountryStateUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $areaCountryStateUuids, array $errors = [])
    {
        $this->areaCountryStateUuids = $areaCountryStateUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getAreaCountryStateUuids(): array
    {
        return $this->areaCountryStateUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }

    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}
