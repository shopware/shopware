<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class StatisticCurrentCustomerWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_current_customer.written';

    /**
     * @var string[]
     */
    private $statisticCurrentCustomerUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $statisticCurrentCustomerUuids, array $errors = [])
    {
        $this->statisticCurrentCustomerUuids = $statisticCurrentCustomerUuids;
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
    public function getStatisticCurrentCustomerUuids(): array
    {
        return $this->statisticCurrentCustomerUuids;
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
