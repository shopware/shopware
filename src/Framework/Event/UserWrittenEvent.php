<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class UserWrittenEvent extends NestedEvent
{
    const NAME = 'user.written';

    /**
     * @var string[]
     */
    private $userUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $userUuids, array $errors = [])
    {
        $this->userUuids = $userUuids;
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
    public function getUserUuids(): array
    {
        return $this->userUuids;
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
