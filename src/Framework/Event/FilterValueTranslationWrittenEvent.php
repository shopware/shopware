<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterValueTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'filter_value_translation.written';

    /**
     * @var string[]
     */
    private $filterValueTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterValueTranslationUuids, array $errors = [])
    {
        $this->filterValueTranslationUuids = $filterValueTranslationUuids;
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
    public function getFilterValueTranslationUuids(): array
    {
        return $this->filterValueTranslationUuids;
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
