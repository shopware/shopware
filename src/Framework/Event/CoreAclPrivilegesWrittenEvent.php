<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreAclPrivilegesWrittenEvent extends NestedEvent
{
    const NAME = 'core_acl_privileges.written';

    /**
     * @var string[]
     */
    private $coreAclPrivilegesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreAclPrivilegesUuids, array $errors = [])
    {
        $this->coreAclPrivilegesUuids = $coreAclPrivilegesUuids;
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
    public function getCoreAclPrivilegesUuids(): array
    {
        return $this->coreAclPrivilegesUuids;
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
