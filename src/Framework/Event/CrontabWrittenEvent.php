<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CrontabWrittenEvent extends NestedEvent
{
    const NAME = 'crontab.written';

    /**
     * @var string[]
     */
    private $crontabUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $crontabUuids, array $errors = [])
    {
        $this->crontabUuids = $crontabUuids;
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
    public function getCrontabUuids(): array
    {
        return $this->crontabUuids;
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
