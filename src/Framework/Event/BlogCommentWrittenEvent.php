<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BlogCommentWrittenEvent extends NestedEvent
{
    const NAME = 'blog_comment.written';

    /**
     * @var string[]
     */
    private $blogCommentUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $blogCommentUuids, array $errors = [])
    {
        $this->blogCommentUuids = $blogCommentUuids;
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
    public function getBlogCommentUuids(): array
    {
        return $this->blogCommentUuids;
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
