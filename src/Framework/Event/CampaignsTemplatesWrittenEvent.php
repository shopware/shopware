<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsTemplatesWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_templates.written';

    /**
     * @var string[]
     */
    private $campaignsTemplatesUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsTemplatesUuids, array $errors = [])
    {
        $this->campaignsTemplatesUuids = $campaignsTemplatesUuids;
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
    public function getCampaignsTemplatesUuids(): array
    {
        return $this->campaignsTemplatesUuids;
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
