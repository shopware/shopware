<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class ExportArticlesWrittenEvent extends NestedEvent
{
    const NAME = 'export_articles.written';

    /**
     * @var string[]
     */
    private $exportArticlesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $exportArticlesUuids, array $errors = [])
    {
        $this->exportArticlesUuids = $exportArticlesUuids;
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
    public function getExportArticlesUuids(): array
    {
        return $this->exportArticlesUuids;
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
