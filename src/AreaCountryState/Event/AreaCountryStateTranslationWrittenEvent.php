<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryStateTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'area_country_state_translation.written';

    /**
     * @var string[]
     */
    private $areaCountryStateTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $areaCountryStateTranslationUuids, array $errors = [])
    {
        $this->areaCountryStateTranslationUuids = $areaCountryStateTranslationUuids;
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
    public function getAreaCountryStateTranslationUuids(): array
    {
        return $this->areaCountryStateTranslationUuids;
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
