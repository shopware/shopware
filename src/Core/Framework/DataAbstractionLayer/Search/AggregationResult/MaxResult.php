<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class MaxResult extends AbstractAggregationResult
{
    /**
     * @var float
     */
    protected $max;

    public function __construct(?array $key, float $max)
    {
        parent::__construct($key);
        $this->max = $max;
    }

    public function getMax(): float
    {
        return $this->max;
    }
}
