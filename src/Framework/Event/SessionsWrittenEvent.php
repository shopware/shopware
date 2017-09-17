<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class SessionsWrittenEvent extends NestedEvent
{
    const NAME = 'sessions.written';

    /**
     * @var string[]
     */
    private $sessionsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $sessionsUuids, array $errors = [])
    {
        $this->sessionsUuids = $sessionsUuids;
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
    public function getSessionsUuids(): array
    {
        return $this->sessionsUuids;
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
