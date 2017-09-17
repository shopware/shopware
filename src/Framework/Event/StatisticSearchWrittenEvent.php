<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class StatisticSearchWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_search.written';

    /**
     * @var string[]
     */
    private $statisticSearchUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $statisticSearchUuids, array $errors = [])
    {
        $this->statisticSearchUuids = $statisticSearchUuids;
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
    public function getStatisticSearchUuids(): array
    {
        return $this->statisticSearchUuids;
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
