<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BlogTagWrittenEvent extends NestedEvent
{
    const NAME = 'blog_tag.written';

    /**
     * @var string[]
     */
    private $blogTagUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $blogTagUuids, array $errors = [])
    {
        $this->blogTagUuids = $blogTagUuids;
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
    public function getBlogTagUuids(): array
    {
        return $this->blogTagUuids;
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
