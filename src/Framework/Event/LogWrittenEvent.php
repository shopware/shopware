<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class LogWrittenEvent extends NestedEvent
{
    const NAME = 'log.written';

    /**
     * @var string[]
     */
    private $logUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $logUuids, array $errors = [])
    {
        $this->logUuids = $logUuids;
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
    public function getLogUuids(): array
    {
        return $this->logUuids;
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
