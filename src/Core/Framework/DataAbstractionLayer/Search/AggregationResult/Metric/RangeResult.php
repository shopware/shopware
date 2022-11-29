<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final tag:v6.5.0
 */
class RangeResult extends AggregationResult
{
    /**
     * @var array<string, int>
     */
    protected array $ranges = [];

    /**
     * @param array<string, int> $ranges
     */
    public function __construct(string $name, array $ranges)
    {
        parent::__construct($name);
        $this->ranges = $ranges;
    }

    /**
     * @return array<string, int>
     */
    public function getRanges(): array
    {
        return $this->ranges;
    }
}
