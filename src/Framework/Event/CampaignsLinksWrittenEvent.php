<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsLinksWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_links.written';

    /**
     * @var string[]
     */
    private $campaignsLinksUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsLinksUuids, array $errors = [])
    {
        $this->campaignsLinksUuids = $campaignsLinksUuids;
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
    public function getCampaignsLinksUuids(): array
    {
        return $this->campaignsLinksUuids;
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
