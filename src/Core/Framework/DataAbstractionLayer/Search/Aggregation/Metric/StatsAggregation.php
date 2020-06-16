<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;

class StatsAggregation extends Aggregation
{
    /**
     * @var bool
     */
    private $max;

    /**
     * @var bool
     */
    private $min;

    /**
     * @var bool
     */
    private $sum;

    /**
     * @var bool
     */
    private $avg;

    public function __construct(string $name, string $field, bool $max = true, bool $min = true, bool $sum = true, bool $avg = true)
    {
        parent::__construct($name, $field);
        $this->max = $max;
        $this->min = $min;
        $this->sum = $sum;
        $this->avg = $avg;
    }

    public function fetchMax(): bool
    {
        return $this->max;
    }

    public function fetchMin(): bool
    {
        return $this->min;
    }

    public function fetchSum(): bool
    {
        return $this->sum;
    }

    public function fetchAvg(): bool
    {
        return $this->avg;
    }
}
