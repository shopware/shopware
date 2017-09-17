<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class SchemaVersionWrittenEvent extends NestedEvent
{
    const NAME = 'schema_version.written';

    /**
     * @var string[]
     */
    private $schemaVersionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $schemaVersionUuids, array $errors = [])
    {
        $this->schemaVersionUuids = $schemaVersionUuids;
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
    public function getSchemaVersionUuids(): array
    {
        return $this->schemaVersionUuids;
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
