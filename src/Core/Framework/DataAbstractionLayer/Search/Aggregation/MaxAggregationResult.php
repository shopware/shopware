<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class MaxAggregationResult extends AggregationResult
{
    /**
     * @var float
     */
    protected $max;

    public function __construct(Aggregation $aggregation, float $max)
    {
        parent::__construct($aggregation);

        $this->max = $max;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getResult(): array
    {
        return ['max' => $this->max];
    }
}
