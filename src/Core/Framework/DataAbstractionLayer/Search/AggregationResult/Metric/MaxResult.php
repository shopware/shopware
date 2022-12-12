<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final
 */
class MaxResult extends AggregationResult
{
    public function __construct(string $name, protected string|float|int|null $max)
    {
        parent::__construct($name);
    }

    public function getMax(): string|float|int|null
    {
        return $this->max;
    }
}
