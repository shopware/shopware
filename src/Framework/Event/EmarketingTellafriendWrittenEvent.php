<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmarketingTellafriendWrittenEvent extends NestedEvent
{
    const NAME = 'emarketing_tellafriend.written';

    /**
     * @var string[]
     */
    private $emarketingTellafriendUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emarketingTellafriendUuids, array $errors = [])
    {
        $this->emarketingTellafriendUuids = $emarketingTellafriendUuids;
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
    public function getEmarketingTellafriendUuids(): array
    {
        return $this->emarketingTellafriendUuids;
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
