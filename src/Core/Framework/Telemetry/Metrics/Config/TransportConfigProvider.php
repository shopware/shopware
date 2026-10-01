<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TransportConfigProvider
{
    public function __construct(
        private readonly MetricConfigProvider $metricConfigProvider,
        private readonly ?string $namespace = null,
    ) {
    }

    public function getTransportConfig(): TransportConfig
    {
        return new TransportConfig(
            metricsConfig: $this->metricConfigProvider->all(),
            namespace: $this->namespace,
        );
    }
}
