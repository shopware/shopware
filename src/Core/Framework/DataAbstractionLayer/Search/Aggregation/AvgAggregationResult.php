<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class AvgAggregationResult extends AggregationResult
{
    /**
     * @var float
     */
    protected $average;

    public function __construct(Aggregation $aggregation, float $average)
    {
        parent::__construct($aggregation);

        $this->average = $average;
    }

    public function getAverage(): float
    {
        return $this->average;
    }

    public function getResult(): array
    {
        return ['avg' => $this->average];
    }
}
