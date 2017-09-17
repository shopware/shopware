<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ExportCategoriesWrittenEvent extends NestedEvent
{
    const NAME = 'export_categories.written';

    /**
     * @var string[]
     */
    private $exportCategoriesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $exportCategoriesUuids, array $errors = [])
    {
        $this->exportCategoriesUuids = $exportCategoriesUuids;
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
    public function getExportCategoriesUuids(): array
    {
        return $this->exportCategoriesUuids;
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
