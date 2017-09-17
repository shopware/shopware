<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ListingSortingTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'listing_sorting_translation.written';

    /**
     * @var string[]
     */
    private $listingSortingTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $listingSortingTranslationUuids, array $errors = [])
    {
        $this->listingSortingTranslationUuids = $listingSortingTranslationUuids;
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
    public function getListingSortingTranslationUuids(): array
    {
        return $this->listingSortingTranslationUuids;
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
