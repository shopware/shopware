<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsSenderWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_sender.written';

    /**
     * @var string[]
     */
    private $campaignsSenderUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsSenderUuids, array $errors = [])
    {
        $this->campaignsSenderUuids = $campaignsSenderUuids;
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
    public function getCampaignsSenderUuids(): array
    {
        return $this->campaignsSenderUuids;
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
