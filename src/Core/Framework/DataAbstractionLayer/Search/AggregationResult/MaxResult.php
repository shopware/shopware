<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class MaxResult extends AbstractAggregationResult
{
    /**
     * @var mixed
     */
    protected $max;

    public function __construct(?array $key, $max)
    {
        parent::__construct($key);
        $this->max = $max;
    }

    public function getMax()
    {
        return $this->max;
    }
}
