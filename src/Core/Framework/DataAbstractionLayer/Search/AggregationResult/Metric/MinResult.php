<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class MinResult extends AggregationResult
{
    protected $min;

    public function __construct(string $name, $min)
    {
        parent::__construct($name);
        $this->min = $min;
    }

    public function getMin()
    {
        return $this->min;
    }
}
