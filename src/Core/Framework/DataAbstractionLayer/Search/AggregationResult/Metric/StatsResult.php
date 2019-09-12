<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class StatsResult extends AggregationResult
{
    /**
     * @var mixed|null
     */
    protected $min;

    /**
     * @var mixed|null
     */
    protected $max;

    /**
     * @var float|null
     */
    protected $avg;

    /**
     * @var float|null
     */
    protected $sum;

    public function __construct(string $name, $min, $max, ?float $avg, ?float $sum)
    {
        parent::__construct($name);
        $this->min = $min;
        $this->max = $max;
        $this->avg = $avg;
        $this->sum = $sum;
    }

    public function getMin()
    {
        return $this->min;
    }

    public function getMax()
    {
        return $this->max;
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
