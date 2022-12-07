<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final
 */
class MinResult extends AggregationResult
{
    public function __construct(string $name, protected float|int|string|null $min)
    {
        parent::__construct($name);
    }

    public function getMin(): float|int|string|null
    {
        return $this->min;
    }
}
