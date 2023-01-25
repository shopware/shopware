<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class StatsAggregation extends Aggregation
{
    public function __construct(
        string $name,
        string $field,
        private readonly bool $max = true,
        private readonly bool $min = true,
        private readonly bool $sum = true,
        private readonly bool $avg = true
    ) {
        parent::__construct($name, $field);
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
