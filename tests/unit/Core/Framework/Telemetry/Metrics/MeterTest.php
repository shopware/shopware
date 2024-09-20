<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Meter::class)]
class MeterTest extends TestCase
{
    public function testEmit(): void
    {
        [$configuredMetric, $metricConfig, $_, $transportCall] = $this->buildCommonTestStubs();
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects(static::once())->method('emit')->with($transportCall);
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects(static::once())->method('emit')->with($transportCall);

        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $meter = new Meter($collection, $this->configProviderWithSuccessfulExpectation($metricConfig), $this->createMock(LoggerInterface::class), 'prod');
        $meter->emit($configuredMetric);
    }

    public function testTransportErrorDoesNotBreakApplication(): void
    {
        [$configuredMetric, $metricConfig, $_, $transportCall] = $this->buildCommonTestStubs();
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects(static::once())->method('emit')->with($transportCall)->willThrowException(new \RuntimeException('Transport failed'));
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects(static::once())->method('emit')->with($transportCall);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning');
        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $meter = new Meter($collection, $this->configProviderWithSuccessfulExpectation($metricConfig), $logger, 'prod');
        $meter->emit($configuredMetric);
    }

    public function testMetricNotSupportedException(): void
    {
        [$configuredMetric, $metricConfig, $metric, $transportCall] = $this->buildCommonTestStubs();

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects(static::once())
            ->method('emit')
            ->with($transportCall)
            ->willThrowException(TelemetryException::metricNotSupported($metric, $transport));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                static::stringContains('Metric'),
                static::arrayHasKey('exception')
            );

        $collection = $this->createTransportCollectionMock([$transport]);

        $meter = new Meter($collection, $this->configProviderWithSuccessfulExpectation($metricConfig), $logger, 'prod');
        $meter->emit($configuredMetric);
    }

    public function testImproperlyConfiguredMetricIsNotEmitted(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('error')
            ->with(
                static::stringContains('Missing configuration'),
                static::arrayHasKey('exception')
            );

        $configuredMetric = new ConfiguredMetric('test', 1, ['test' => 'test']);

        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects(static::once())
            ->method('get')
            ->with('test')
            ->willThrowException(TelemetryException::metricMissingConfiguration('test'));

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects(static::never())->method('emit');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects(static::never())->method('getIterator');

        $meter = new Meter($collection, $metricConfigProvider, $logger, 'prod');
        $meter->emit($configuredMetric);
    }

    /**
     * @param array<MetricTransportInterface> $transports
     *
     * @return TransportCollection<MetricTransportInterface>
     */
    private function createTransportCollectionMock(array $transports): TransportCollection
    {
        $collection = $this->createMock(TransportCollection::class);
        $collection->expects(static::once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($transports));

        return $collection;
    }

    /**
     * @return array{ConfiguredMetric, MetricConfig, Metric, Callback<Metric>}
     */
    public function buildCommonTestStubs(): array
    {
        $configuredMetric = new ConfiguredMetric('test', 1, ['test' => 'test']);
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: [], unit: 'unit');
        $metric = new Metric($configuredMetric, $metricConfig);
        $transportCall = static::callback(function (Metric $inputMetric) use ($metric) {
            self::assertEquals($metric, $inputMetric);

            return true;
        });

        return [$configuredMetric, $metricConfig, $metric, $transportCall];
    }

    public function configProviderWithSuccessfulExpectation(mixed $metricConfig): MetricConfigProvider&MockObject
    {
        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects(static::once())->method('get')->with('test')->willReturn($metricConfig);

        return $metricConfigProvider;
    }
}
