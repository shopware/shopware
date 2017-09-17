<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class BlogProductWrittenEvent extends NestedEvent
{
    const NAME = 'blog_product.written';

    /**
     * @var string[]
     */
    private $blogProductUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $blogProductUuids, array $errors = [])
    {
        $this->blogProductUuids = $blogProductUuids;
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
    public function getBlogProductUuids(): array
    {
        return $this->blogProductUuids;
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
