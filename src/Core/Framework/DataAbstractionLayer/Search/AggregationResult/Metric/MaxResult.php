<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class MaxResult extends AggregationResult
{
    protected $max;

    public function __construct(string $name, $max)
    {
        parent::__construct($name);
        $this->max = $max;
    }

    public function getMax()
    {
        return $this->max;
    }
}
