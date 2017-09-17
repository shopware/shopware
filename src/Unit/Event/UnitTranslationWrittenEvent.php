<?php declare(strict_types=1);

namespace Shopware\Unit\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class UnitTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'unit_translation.written';

    /**
     * @var string[]
     */
    private $unitTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $unitTranslationUuids, array $errors = [])
    {
        $this->unitTranslationUuids = $unitTranslationUuids;
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
    public function getUnitTranslationUuids(): array
    {
        return $this->unitTranslationUuids;
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
