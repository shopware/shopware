<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class StatisticVisitorWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_visitor.written';

    /**
     * @var string[]
     */
    private $statisticVisitorUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $statisticVisitorUuids, array $errors = [])
    {
        $this->statisticVisitorUuids = $statisticVisitorUuids;
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
    public function getStatisticVisitorUuids(): array
    {
        return $this->statisticVisitorUuids;
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
