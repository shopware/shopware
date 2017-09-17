<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreCustomerpricegroupsWrittenEvent extends NestedEvent
{
    const NAME = 'core_customerpricegroups.written';

    /**
     * @var string[]
     */
    private $coreCustomerpricegroupsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreCustomerpricegroupsUuids, array $errors = [])
    {
        $this->coreCustomerpricegroupsUuids = $coreCustomerpricegroupsUuids;
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
    public function getCoreCustomerpricegroupsUuids(): array
    {
        return $this->coreCustomerpricegroupsUuids;
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
