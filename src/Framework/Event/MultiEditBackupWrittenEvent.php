<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MultiEditBackupWrittenEvent extends NestedEvent
{
    const NAME = 'multi_edit_backup.written';

    /**
     * @var string[]
     */
    private $multiEditBackupUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $multiEditBackupUuids, array $errors = [])
    {
        $this->multiEditBackupUuids = $multiEditBackupUuids;
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
    public function getMultiEditBackupUuids(): array
    {
        return $this->multiEditBackupUuids;
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
