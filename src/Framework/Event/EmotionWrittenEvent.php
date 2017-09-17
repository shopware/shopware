<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionWrittenEvent extends NestedEvent
{
    const NAME = 'emotion.written';

    /**
     * @var string[]
     */
    private $emotionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionUuids, array $errors = [])
    {
        $this->emotionUuids = $emotionUuids;
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
    public function getEmotionUuids(): array
    {
        return $this->emotionUuids;
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
