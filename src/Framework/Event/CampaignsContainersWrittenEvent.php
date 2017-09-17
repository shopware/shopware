<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsContainersWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_containers.written';

    /**
     * @var string[]
     */
    private $campaignsContainersUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsContainersUuids, array $errors = [])
    {
        $this->campaignsContainersUuids = $campaignsContainersUuids;
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
    public function getCampaignsContainersUuids(): array
    {
        return $this->campaignsContainersUuids;
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
