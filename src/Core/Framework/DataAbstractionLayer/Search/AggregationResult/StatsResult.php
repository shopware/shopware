<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class StatsResult extends AbstractAggregationResult
{
    /**
     * @var float|null
     */
    protected $min;

    /**
     * @var float|null
     */
    protected $max;

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
    protected $sum;

    public function __construct(?array $key, ?float $min, ?float $max, ?int $count, ?float $avg, ?float $sum)
    {
        parent::__construct($key);
        $this->min = $min;
        $this->max = $max;
        $this->count = $count;
        $this->avg = $avg;
        $this->sum = $sum;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function getAvg(): ?float
    {
        return $this->avg;
    }

    public function getSum(): ?float
    {
        return $this->sum;
    }
}
