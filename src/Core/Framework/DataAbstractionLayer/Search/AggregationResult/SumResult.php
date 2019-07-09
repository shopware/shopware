<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class SumResult extends AbstractAggregationResult
{
    /**
     * @var float
     */
    protected $sum;

    public function __construct(?array $key, float $sum)
    {
        parent::__construct($key);
        $this->sum = $sum;
    }

    public function getSum(): float
    {
        return $this->sum;
    }
}
