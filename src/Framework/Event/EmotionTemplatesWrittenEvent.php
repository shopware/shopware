<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionTemplatesWrittenEvent extends NestedEvent
{
    const NAME = 'emotion_templates.written';

    /**
     * @var string[]
     */
    private $emotionTemplatesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionTemplatesUuids, array $errors = [])
    {
        $this->emotionTemplatesUuids = $emotionTemplatesUuids;
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
    public function getEmotionTemplatesUuids(): array
    {
        return $this->emotionTemplatesUuids;
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
