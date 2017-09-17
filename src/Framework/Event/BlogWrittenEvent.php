<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BlogWrittenEvent extends NestedEvent
{
    const NAME = 'blog.written';

    /**
     * @var string[]
     */
    private $blogUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $blogUuids, array $errors = [])
    {
        $this->blogUuids = $blogUuids;
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
    public function getBlogUuids(): array
    {
        return $this->blogUuids;
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
