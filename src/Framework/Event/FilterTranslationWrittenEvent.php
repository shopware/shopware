<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'filter_translation.written';

    /**
     * @var string[]
     */
    private $filterTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterTranslationUuids, array $errors = [])
    {
        $this->filterTranslationUuids = $filterTranslationUuids;
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
    public function getFilterTranslationUuids(): array
    {
        return $this->filterTranslationUuids;
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
