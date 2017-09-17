<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class CampaignsBannerWrittenEvent extends NestedEvent
{
    const NAME = 'campaigns_banner.written';

    /**
     * @var string[]
     */
    private $campaignsBannerUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $campaignsBannerUuids, array $errors = [])
    {
        $this->campaignsBannerUuids = $campaignsBannerUuids;
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
    public function getCampaignsBannerUuids(): array
    {
        return $this->campaignsBannerUuids;
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
