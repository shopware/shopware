<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreRewriteUrlsWrittenEvent extends NestedEvent
{
    const NAME = 'core_rewrite_urls.written';

    /**
     * @var string[]
     */
    private $coreRewriteUrlsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreRewriteUrlsUuids, array $errors = [])
    {
        $this->coreRewriteUrlsUuids = $coreRewriteUrlsUuids;
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
    public function getCoreRewriteUrlsUuids(): array
    {
        return $this->coreRewriteUrlsUuids;
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
