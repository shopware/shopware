<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Telemetry\Factory;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;

/**
 * @internal
 */
#[Package('core')]
class TraceableTransportFactory implements MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        return new TraceableTransport();
    }
}
