<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search\Aggregation;

class StatsAggregationResult extends AggregationResult
{
    /**
     * @var int|null
     */
    protected $count;

    /**
     * @var float|null
     */
    protected $avg;

    /**
     * @var float|null
     */
    protected $min;

    /**
     * @var float|null
     */
    protected $max;

    /**
     * @var float|null
     */
    protected $sum;

    public function __construct(
        Aggregation $aggregation,
        ?int $count = null,
        ?float $avg = null,
        ?float $sum = null,
        ?float $min = null,
        ?float $max = null
    ) {
        parent::__construct($aggregation);

        $this->count = $count;
        $this->avg = $avg;
        $this->min = $min;
        $this->max = $max;
        $this->sum = $sum;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getAvg(): float
    {
        return $this->avg;
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getSum(): float
    {
        return $this->sum;
    }

    public function getResult(): array
    {
        return [
            'count' => $this->count,
            'avg' => $this->avg,
            'min' => $this->min,
            'max' => $this->max,
            'sum' => $this->sum,
        ];
    }
}
