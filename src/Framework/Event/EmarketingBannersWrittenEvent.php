<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class EmarketingBannersWrittenEvent extends NestedEvent
{
    const NAME = 'emarketing_banners.written';

    /**
     * @var string[]
     */
    private $emarketingBannersUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $emarketingBannersUuids, array $errors = [])
    {
        $this->emarketingBannersUuids = $emarketingBannersUuids;
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
    public function getEmarketingBannersUuids(): array
    {
        return $this->emarketingBannersUuids;
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
