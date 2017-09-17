<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmarketingPartnerWrittenEvent extends NestedEvent
{
    const NAME = 'emarketing_partner.written';

    /**
     * @var string[]
     */
    private $emarketingPartnerUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emarketingPartnerUuids, array $errors = [])
    {
        $this->emarketingPartnerUuids = $emarketingPartnerUuids;
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
    public function getEmarketingPartnerUuids(): array
    {
        return $this->emarketingPartnerUuids;
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
