<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * Allow to aggregate data on predefined ranges values
 *
 * Bound are computed as in the elasticsearch range aggregation :
 *    the "from" will be compared with greater than or equal
 *    the "to" will be compared with lower than
 */
#[Package('core')]
final class RangeAggregation extends Aggregation
{
    /**
     * @var array<int, array<string, float|string|null>>
     */
    protected array $ranges;

    /**
     * @param array<int, array<string, float|string|null>> $ranges
     */
    public function __construct(
        string $name,
        string $field,
        array $ranges
    ) {
        parent::__construct($name, $field);
        foreach ($ranges as $range) {
            $this->addRange(
                isset($range['from']) ? (float) $range['from'] : null,
                isset($range['to']) ? (float) $range['to'] : null,
                isset($range['key']) ? (string) $range['key'] : null,
            );
        }
    }

    /**
     * @return array<int, array<string, float|string|null>>
     */
    public function getRanges(): array
    {
        return $this->ranges;
    }

    /**
     * Add a new range in the aggregation.
     * If no key is provided, the key will be build using the $from and $to bounds ($this->buildRangeKey())
     */
    public function addRange(?float $from = null, ?float $to = null, ?string $key = null): self
    {
        $this->ranges[] = [
            'from' => $from,
            'to' => $to,
            'key' => $key ?? $this->buildRangeKey($from, $to),
        ];

        return $this;
    }

    /**
     * Build a range dynamic key using $from and $to bound
     */
    protected function buildRangeKey(?float $from = null, ?float $to = null): string
    {
        return ($from === null ? '*' : (string) $from) . '-' . ($to === null ? '*' : (string) $to);
    }
}
