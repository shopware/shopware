<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class AvgResult extends AggregationResult
{
    /**
     * @var float
     */
    protected $avg;

    public function __construct(string $name, float $avg)
    {
        parent::__construct($name);
        $this->avg = $avg;
    }

    public function getAvg(): float
    {
        return $this->avg;
    }
}
