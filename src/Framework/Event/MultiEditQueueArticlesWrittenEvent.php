<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class MultiEditQueueArticlesWrittenEvent extends NestedEvent
{
    const NAME = 'multi_edit_queue_articles.written';

    /**
     * @var string[]
     */
    private $multiEditQueueArticlesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $multiEditQueueArticlesUuids, array $errors = [])
    {
        $this->multiEditQueueArticlesUuids = $multiEditQueueArticlesUuids;
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
    public function getMultiEditQueueArticlesUuids(): array
    {
        return $this->multiEditQueueArticlesUuids;
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
