<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreOptinWrittenEvent extends NestedEvent
{
    const NAME = 'core_optin.written';

    /**
     * @var string[]
     */
    private $coreOptinUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreOptinUuids, array $errors = [])
    {
        $this->coreOptinUuids = $coreOptinUuids;
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
    public function getCoreOptinUuids(): array
    {
        return $this->coreOptinUuids;
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
