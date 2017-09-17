<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

class StatisticProductImpressionWrittenEvent extends NestedEvent
{
    const NAME = 'statistic_product_impression.written';

    /**
     * @var string[]
     */
    private $statisticProductImpressionUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $statisticProductImpressionUuids, array $errors = [])
    {
        $this->statisticProductImpressionUuids = $statisticProductImpressionUuids;
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
    public function getStatisticProductImpressionUuids(): array
    {
        return $this->statisticProductImpressionUuids;
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
