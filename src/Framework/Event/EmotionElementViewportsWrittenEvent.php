<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmotionElementViewportsWrittenEvent extends NestedEvent
{
    const NAME = 'emotion_element_viewports.written';

    /**
     * @var string[]
     */
    private $emotionElementViewportsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emotionElementViewportsUuids, array $errors = [])
    {
        $this->emotionElementViewportsUuids = $emotionElementViewportsUuids;
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
    public function getEmotionElementViewportsUuids(): array
    {
        return $this->emotionElementViewportsUuids;
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
