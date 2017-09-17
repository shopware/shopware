<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CoreTranslationsWrittenEvent extends NestedEvent
{
    const NAME = 'core_translations.written';

    /**
     * @var string[]
     */
    private $coreTranslationsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $coreTranslationsUuids, array $errors = [])
    {
        $this->coreTranslationsUuids = $coreTranslationsUuids;
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
    public function getCoreTranslationsUuids(): array
    {
        return $this->coreTranslationsUuids;
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
