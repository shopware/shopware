<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class AvgResult extends AbstractAggregationResult
{
    /**
     * @var float
     */
    protected $avg;

    public function __construct(?array $key, float $avg)
    {
        parent::__construct($key);
        $this->avg = $avg;
    }

    public function getAvg(): float
    {
        return $this->avg;
    }
}
