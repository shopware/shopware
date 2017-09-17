<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsPositionsWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_positions.written';

    /**
     * @var string[]
     */
    private $campaignsPositionsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsPositionsUuids, array $errors = [])
    {
        $this->campaignsPositionsUuids = $campaignsPositionsUuids;
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
    public function getCampaignsPositionsUuids(): array
    {
        return $this->campaignsPositionsUuids;
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
