<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class MinAggregationResult extends AggregationResult
{
    /**
     * @var float
     */
    protected $min;

    public function __construct(Aggregation $aggregation, float $min)
    {
        parent::__construct($aggregation);

        $this->min = $min;
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getResult(): array
    {
        return ['min' => $this->min];
    }
}
