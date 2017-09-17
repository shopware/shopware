<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class StatisticAddressPoolWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_address_pool.written';

    /**
     * @var string[]
     */
    private $statisticAddressPoolUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $statisticAddressPoolUuids, array $errors = [])
    {
        $this->statisticAddressPoolUuids = $statisticAddressPoolUuids;
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
    public function getStatisticAddressPoolUuids(): array
    {
        return $this->statisticAddressPoolUuids;
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
