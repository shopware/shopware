<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BlogTagTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'blog_tag_translation.written';

    /**
     * @var string[]
     */
    private $blogTagTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $blogTagTranslationUuids, array $errors = [])
    {
        $this->blogTagTranslationUuids = $blogTagTranslationUuids;
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
    public function getBlogTagTranslationUuids(): array
    {
        return $this->blogTagTranslationUuids;
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
