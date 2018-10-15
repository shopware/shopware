<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class SumAggregationResult extends AggregationResult
{
    /**
     * @var float
     */
    protected $sum;

    public function __construct(Aggregation $aggregation, float $sum)
    {
        parent::__construct($aggregation);
        $this->sum = $sum;
    }

    public function getSum(): float
    {
        return $this->sum;
    }

    public function getResult(): array
    {
        return ['sum' => $this->sum];
    }
}
