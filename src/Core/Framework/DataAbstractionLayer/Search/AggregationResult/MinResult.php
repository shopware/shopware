<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class MinResult extends AbstractAggregationResult
{
    /**
     * @var float
     */
    protected $min;

    public function __construct(?array $key, float $min)
    {
        parent::__construct($key);
        $this->min = $min;
    }

    public function getMin(): float
    {
        return $this->min;
    }
}
