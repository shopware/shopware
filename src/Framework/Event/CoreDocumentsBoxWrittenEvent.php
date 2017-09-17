<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreDocumentsBoxWrittenEvent extends NestedEvent
{
    const NAME = 'core_documents_box.written';

    /**
     * @var string[]
     */
    private $coreDocumentsBoxUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreDocumentsBoxUuids, array $errors = [])
    {
        $this->coreDocumentsBoxUuids = $coreDocumentsBoxUuids;
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
    public function getCoreDocumentsBoxUuids(): array
    {
        return $this->coreDocumentsBoxUuids;
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
