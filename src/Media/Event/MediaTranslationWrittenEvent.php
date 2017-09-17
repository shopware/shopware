<?php declare(strict_types=1);

namespace Shopware\Media\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MediaTranslationWrittenEvent extends NestedEvent
{
    const NAME = 'media_translation.written';

    /**
     * @var string[]
     */
    private $mediaTranslationUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $mediaTranslationUuids, array $errors = [])
    {
        $this->mediaTranslationUuids = $mediaTranslationUuids;
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
    public function getMediaTranslationUuids(): array
    {
        return $this->mediaTranslationUuids;
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
