<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionElementValueWrittenEvent extends NestedEvent
{
    const NAME = 'emotion_element_value.written';

    /**
     * @var string[]
     */
    private $emotionElementValueUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionElementValueUuids, array $errors = [])
    {
        $this->emotionElementValueUuids = $emotionElementValueUuids;
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
    public function getEmotionElementValueUuids(): array
    {
        return $this->emotionElementValueUuids;
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
