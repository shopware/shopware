<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class SessionWrittenEvent extends NestedEvent
{
    const NAME = 'session.written';

    /**
     * @var string[]
     */
    private $sessionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $sessionUuids, array $errors = [])
    {
        $this->sessionUuids = $sessionUuids;
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
    public function getSessionUuids(): array
    {
        return $this->sessionUuids;
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
