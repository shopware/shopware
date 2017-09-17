<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EsBacklogWrittenEvent extends NestedEvent
{
    const NAME = 'es_backlog.written';

    /**
     * @var string[]
     */
    private $esBacklogUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $esBacklogUuids, array $errors = [])
    {
        $this->esBacklogUuids = $esBacklogUuids;
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
    public function getEsBacklogUuids(): array
    {
        return $this->esBacklogUuids;
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
