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
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricLabelProcessor;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Meter::class)]
class MeterTest extends TestCase
{
    public function testEmit(): void
    {
        [$configuredMetric, $metricConfig, $_, $transportCall] = $this->buildCommonTestStubs();
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects($this->once())->method('emit')->with($transportCall);
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects($this->once())->method('emit')->with($transportCall);

        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $this->createPassthroughLabelProcessor(),
            $this->createMock(LoggerInterface::class),
            'prod',
            true,
        );
        $meter->emit($configuredMetric);
    }

    public function testEmitDoesNothingWhenDisabled(): void
    {
        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->never())->method('emit');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $configProvider = $this->createMock(MetricConfigProvider::class);
        $configProvider->expects($this->never())->method('get');

        $labelProcessor = $this->createMock(MetricLabelProcessor::class);
        $labelProcessor->expects($this->never())->method('process');

        $meter = new Meter(
            $collection,
            $configProvider,
            $labelProcessor,
            $this->createMock(LoggerInterface::class),
            'prod',
            false,
        );

        $meter->emit(new ConfiguredMetric('test', 1));
    }

    public function testTransportErrorDoesNotBreakApplication(): void
    {
        [$configuredMetric, $metricConfig, $_, $transportCall] = $this->buildCommonTestStubs();
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects($this->once())->method('emit')->with($transportCall)->willThrowException(new \RuntimeException('Transport failed'));
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects($this->once())->method('emit')->with($transportCall);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $collection = $this->createTransportCollectionMock([$transport1, $transport2]);

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $this->createPassthroughLabelProcessor(),
            $logger,
            'prod',
            true,
        );
        $meter->emit($configuredMetric);
    }

    public function testMetricNotSupportedException(): void
    {
        [$configuredMetric, $metricConfig, $metric, $transportCall] = $this->buildCommonTestStubs();

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->once())
            ->method('emit')
            ->with($transportCall)
            ->willThrowException(TelemetryException::metricNotSupported($metric, $transport));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                static::stringContains('Metric'),
                static::arrayHasKey('exception')
            );

        $collection = $this->createTransportCollectionMock([$transport]);

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $this->createPassthroughLabelProcessor(),
            $logger,
            'prod',
            true,
        );
        $meter->emit($configuredMetric);
    }

    public function testImproperlyConfiguredMetricIsNotEmitted(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('Missing configuration'),
                static::arrayHasKey('exception')
            );

        $configuredMetric = new ConfiguredMetric('test', 1, ['test' => 'test']);

        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects($this->once())
            ->method('get')
            ->with('test')
            ->willThrowException(TelemetryException::metricMissingConfiguration('test'));

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->never())->method('emit');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $metricConfigProvider,
            $this->createMock(MetricLabelProcessor::class),
            $logger,
            'prod',
            true,
        );
        $meter->emit($configuredMetric);
    }

    public function testImproperlyConfiguredMetricIsRethrownInTestEnv(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('Missing configuration'),
                static::arrayHasKey('exception')
            );

        $configuredMetric = new ConfiguredMetric('test', 1, ['test' => 'test']);

        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects($this->once())
            ->method('get')
            ->with('test')
            ->willThrowException(TelemetryException::metricMissingConfiguration('test'));

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->never())->method('emit');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $metricConfigProvider,
            $this->createMock(MetricLabelProcessor::class),
            $logger,
            'test',
            true,
        );

        $this->expectException(MissingMetricConfigurationException::class);
        $meter->emit($configuredMetric);
    }

    public function testLabelProcessorDiscardPreventsEmission(): void
    {
        $configuredMetric = new ConfiguredMetric('test', 1, ['region' => 'unknown']);
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::GAUGE, enabled: true, parameters: []);

        $labelProcessor = $this->createMock(MetricLabelProcessor::class);
        $labelProcessor->expects($this->once())->method('process')->willReturn(null);

        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->never())->method('emit');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $labelProcessor,
            $this->createMock(LoggerInterface::class),
            'prod',
            true,
        );
        $meter->emit($configuredMetric);
    }

    /**
     * @return array{ConfiguredMetric, MetricConfig, Metric, Callback<Metric>}
     */
    public function buildCommonTestStubs(): array
    {
        $configuredMetric = new ConfiguredMetric('test', 1, ['test' => 'test']);
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: [], unit: 'unit');
        $metric = Metric::fromConfigured($configuredMetric, $metricConfig, ['test' => 'test']);
        $transportCall = static::callback(static function (Metric $inputMetric) use ($metric) {
            self::assertEquals($metric, $inputMetric);

            return true;
        });

        return [$configuredMetric, $metricConfig, $metric, $transportCall];
    }

    public function configProviderWithSuccessfulExpectation(mixed $metricConfig): MetricConfigProvider&MockObject
    {
        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects($this->once())->method('get')->with('test')->willReturn($metricConfig);

        return $metricConfigProvider;
    }

    private function createPassthroughLabelProcessor(): MetricLabelProcessor&MockObject
    {
        $processor = $this->createMock(MetricLabelProcessor::class);
        $processor->method('process')->willReturnCallback(
            static fn (MetricConfig $config, array $labels) => $labels,
        );

        return $processor;
    }

    /**
     * @param array<MetricTransportInterface> $transports
     *
     * @return TransportCollection<MetricTransportInterface>
     */
    private function createTransportCollectionMock(array $transports): TransportCollection
    {
        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($transports));

        return $collection;
    }
}
