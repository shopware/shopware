<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class SnippetWrittenEvent extends NestedEvent
{
    const NAME = 'snippet.written';

    /**
     * @var string[]
     */
    private $snippetUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $snippetUuids, array $errors = [])
    {
        $this->snippetUuids = $snippetUuids;
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
    public function getSnippetUuids(): array
    {
        return $this->snippetUuids;
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
