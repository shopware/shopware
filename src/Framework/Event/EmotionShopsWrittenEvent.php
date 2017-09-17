<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionShopsWrittenEvent extends NestedEvent
{
    const NAME = 'emotion_shops.written';

    /**
     * @var string[]
     */
    private $emotionShopsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionShopsUuids, array $errors = [])
    {
        $this->emotionShopsUuids = $emotionShopsUuids;
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
    public function getEmotionShopsUuids(): array
    {
        return $this->emotionShopsUuids;
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
