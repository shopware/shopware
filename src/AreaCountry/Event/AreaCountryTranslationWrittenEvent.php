<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class AreaCountryTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'area_country_translation.written';

    /**
     * @var string[]
     */
    private $areaCountryTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $areaCountryTranslationUuids, array $errors = [])
    {
        $this->areaCountryTranslationUuids = $areaCountryTranslationUuids;
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
    public function getAreaCountryTranslationUuids(): array
    {
        return $this->areaCountryTranslationUuids;
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
