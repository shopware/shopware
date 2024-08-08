<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
readonly class Histogram implements MetricInterface
{
    public function __construct(
        public string $name,
        public int|float $value,
        public ?string $description = null,
        public ?string $unit = null,
    ) {
    }
}
