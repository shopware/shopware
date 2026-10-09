<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
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
            static::createStub(LoggerInterface::class),
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
            static::createStub(LoggerInterface::class),
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
            static::createStub(MetricLabelProcessor::class),
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
            static::createStub(MetricLabelProcessor::class),
            $logger,
            'test',
            true,
        );

        $this->expectException(MissingMetricConfigurationException::class);
        $meter->emit($configuredMetric);
    }

    public function testIsEnabledReturnsTrueForEnabledMetric(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            static::createStub(MetricLabelProcessor::class),
            static::createStub(LoggerInterface::class),
            'prod',
            true,
        );

        static::assertTrue($meter->isEnabled('test'));
    }

    public function testIsEnabledReturnsFalseForDisabledMetric(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: false, parameters: []);

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            static::createStub(MetricLabelProcessor::class),
            static::createStub(LoggerInterface::class),
            'prod',
            true,
        );

        static::assertFalse($meter->isEnabled('test'));
    }

    public function testIsEnabledReturnsFalseWhenTelemetryDisabled(): void
    {
        $configProvider = $this->createMock(MetricConfigProvider::class);
        $configProvider->expects($this->never())->method('get');

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $configProvider,
            static::createStub(MetricLabelProcessor::class),
            static::createStub(LoggerInterface::class),
            'prod',
            false,
        );

        static::assertFalse($meter->isEnabled('test'));
    }

    public function testIsEnabledLogsAndReturnsFalseForUnconfiguredMetricInProd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('Missing configuration'),
                static::arrayHasKey('exception')
            );

        $configProvider = $this->createMock(MetricConfigProvider::class);
        $configProvider->expects($this->once())
            ->method('get')
            ->with('test')
            ->willThrowException(TelemetryException::metricMissingConfiguration('test'));

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $configProvider,
            static::createStub(MetricLabelProcessor::class),
            $logger,
            'prod',
            true,
        );

        static::assertFalse($meter->isEnabled('test'));
    }

    public function testIsEnabledThrowsForUnconfiguredMetricInTestEnv(): void
    {
        $configProvider = $this->createMock(MetricConfigProvider::class);
        $configProvider->expects($this->once())
            ->method('get')
            ->with('test')
            ->willThrowException(TelemetryException::metricMissingConfiguration('test'));

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $configProvider,
            static::createStub(MetricLabelProcessor::class),
            static::createStub(LoggerInterface::class),
            'test',
            true,
        );

        $this->expectException(MissingMetricConfigurationException::class);
        $meter->isEnabled('test');
    }

    public function testEmitSkipsProcessingForDisabledMetric(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: false, parameters: []);

        $labelProcessor = $this->createMock(MetricLabelProcessor::class);
        $labelProcessor->expects($this->never())->method('process');

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $labelProcessor,
            static::createStub(LoggerInterface::class),
            'prod',
            true,
        );

        $meter->emit(new ConfiguredMetric('test', 1));
    }

    public function testEmitResolvesLabelAndValueClosures(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);

        $emitted = null;
        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects($this->once())->method('emit')->willReturnCallback(static function (Metric $metric) use (&$emitted): void {
            $emitted = $metric;
        });

        $meter = new Meter(
            $this->createTransportCollectionMock([$transport]),
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $this->createPassthroughLabelProcessor(),
            static::createStub(LoggerInterface::class),
            'prod',
            true,
        );

        $meter->emit(new ConfiguredMetric(
            'test',
            static fn (): int => 41 + 1,
            static fn (): array => ['test' => 'lazy'],
        ));

        static::assertNotNull($emitted);
        static::assertSame(42, $emitted->value);
        static::assertSame(['test' => 'lazy'], $emitted->labels);
    }

    public function testDiscardedMetricNeverComputesItsValue(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);

        $labelProcessor = $this->createMock(MetricLabelProcessor::class);
        $labelProcessor->expects($this->once())->method('process')->willReturn(null);

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            $labelProcessor,
            static::createStub(LoggerInterface::class),
            'prod',
            true,
        );

        $meter->emit(new ConfiguredMetric(
            'test',
            static fn (): int => throw new \LogicException('value must not be computed for a discarded metric'),
            ['region' => 'unknown'],
        ));
    }

    public function testThrowingLabelClosureIsLoggedAndSwallowedInProd(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('label error', static::arrayHasKey('exception'));

        $collection = $this->createMock(TransportCollection::class);
        $collection->expects($this->never())->method('getIterator');

        $meter = new Meter(
            $collection,
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            static::createStub(MetricLabelProcessor::class),
            $logger,
            'prod',
            true,
        );

        $meter->emit(new ConfiguredMetric('test', 1, static fn (): array => throw new \RuntimeException('label error')));
    }

    public function testThrowingLabelClosureIsRethrownInTestEnv(): void
    {
        $metricConfig = new MetricConfig(name: 'test', description: 'test', type: Type::COUNTER, enabled: true, parameters: []);

        $meter = new Meter(
            static::createStub(TransportCollection::class),
            $this->configProviderWithSuccessfulExpectation($metricConfig),
            static::createStub(MetricLabelProcessor::class),
            static::createStub(LoggerInterface::class),
            'test',
            true,
        );

        $this->expectExceptionObject(new \RuntimeException('label error'));
        $meter->emit(new ConfiguredMetric('test', 1, static fn (): array => throw new \RuntimeException('label error')));
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
            static::createStub(LoggerInterface::class),
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
        $metric = Metric::fromArray([
            'name' => 'test',
            'type' => Type::COUNTER,
            'value' => 1,
            'labels' => ['test' => 'test'],
            'description' => 'test',
            'unit' => 'unit',
        ]);
        $transportCall = static::callback(static function (Metric $inputMetric) use ($metric) {
            self::assertEquals($metric, $inputMetric);

            return true;
        });

        return [$configuredMetric, $metricConfig, $metric, $transportCall];
    }

    public function configProviderWithSuccessfulExpectation(mixed $metricConfig): MetricConfigProvider&MockObject
    {
        // emit() resolves the config twice: once in the isEnabled() gate, once while processing
        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);
        $metricConfigProvider->expects($this->atLeastOnce())->method('get')->with('test')->willReturn($metricConfig);

        return $metricConfigProvider;
    }

    private function createPassthroughLabelProcessor(): MetricLabelProcessor&Stub
    {
        $processor = static::createStub(MetricLabelProcessor::class);
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
