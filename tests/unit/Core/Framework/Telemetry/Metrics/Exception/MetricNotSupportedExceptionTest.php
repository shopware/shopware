<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MetricNotSupportedException::class)]
class MetricNotSupportedExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $transport = $this->createMock(MetricTransportInterface::class);
        $metricConfig = new MetricConfig('test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);
        $metric = new Metric(new ConfiguredMetric('test', 1, []), $metricConfig);
        $exception = new MetricNotSupportedException($metric, $transport);
        static::assertSame('TELEMETRY__METRIC_NOT_SUPPORTED', $exception->getErrorCode());
    }
}
