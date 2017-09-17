<?php declare(strict_types=1);

namespace Shopware\Holiday\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class HolidayTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'holiday_translation.written';

    /**
     * @var string[]
     */
    private $holidayTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $holidayTranslationUuids, array $errors = [])
    {
        $this->holidayTranslationUuids = $holidayTranslationUuids;
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
    public function getHolidayTranslationUuids(): array
    {
        return $this->holidayTranslationUuids;
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
