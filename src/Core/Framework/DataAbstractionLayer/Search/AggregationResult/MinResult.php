<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class MinResult extends AbstractAggregationResult
{
    /**
     * @var mixed
     */
    protected $min;

    public function __construct(?array $key, $min)
    {
        parent::__construct($key);
        $this->min = $min;
    }

    public function getMin()
    {
        return $this->min;
    }
}
