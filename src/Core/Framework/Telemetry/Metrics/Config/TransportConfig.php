<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
readonly class TransportConfig
{
    /**
     * @param array<MetricConfig> $metricsConfig
     */
    public function __construct(public array $metricsConfig)
    {
    }
}
