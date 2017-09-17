<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class FilterOptionTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'filter_option_translation.written';

    /**
     * @var string[]
     */
    private $filterOptionTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $filterOptionTranslationUuids, array $errors = [])
    {
        $this->filterOptionTranslationUuids = $filterOptionTranslationUuids;
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
    public function getFilterOptionTranslationUuids(): array
    {
        return $this->filterOptionTranslationUuids;
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
