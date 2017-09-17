<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsMailingsWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_mailings.written';

    /**
     * @var string[]
     */
    private $campaignsMailingsUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsMailingsUuids, array $errors = [])
    {
        $this->campaignsMailingsUuids = $campaignsMailingsUuids;
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
    public function getCampaignsMailingsUuids(): array
    {
        return $this->campaignsMailingsUuids;
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
