<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreMenuWrittenEvent extends NestedEvent
{
    const NAME = 'core_menu.written';

    /**
     * @var string[]
     */
    private $coreMenuUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreMenuUuids, array $errors = [])
    {
        $this->coreMenuUuids = $coreMenuUuids;
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
    public function getCoreMenuUuids(): array
    {
        return $this->coreMenuUuids;
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
