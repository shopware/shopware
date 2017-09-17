<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionElementWrittenEvent extends NestedEvent
{
    const NAME = 'emotion_element.written';

    /**
     * @var string[]
     */
    private $emotionElementUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionElementUuids, array $errors = [])
    {
        $this->emotionElementUuids = $emotionElementUuids;
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
    public function getEmotionElementUuids(): array
    {
        return $this->emotionElementUuids;
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
