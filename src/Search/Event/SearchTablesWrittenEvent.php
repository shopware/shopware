<?php declare(strict_types=1);

namespace Shopware\Search\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class SearchTablesWrittenEvent extends NestedEvent
{
    const NAME = 'search_tables.written';

    /**
     * @var string[]
     */
    private $searchTablesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $searchTablesUuids, array $errors = [])
    {
        $this->searchTablesUuids = $searchTablesUuids;
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
    public function getSearchTablesUuids(): array
    {
        return $this->searchTablesUuids;
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
