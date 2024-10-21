<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

use function PHPUnit\Framework\assertInstanceOf;

/**
 * @internal
 */
#[Package('core')]
class MeterTest extends TestCase
{
    use KernelTestBehaviour;

    private TraceableTransport $traceableTransport;

    /**
     * @var array<string, array{type: string, description: string, unit?: string, config?: array<string, mixed>, enabled?: bool}>
     */
    private array $definitions;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        $meter = $this->getContainer()->get(Meter::class);
        $definitions = $this->getContainer()->getParameter('shopware.telemetry.metrics.definitions');
        $transportsCollection = $this->getContainer()->get(TransportCollection::class);
        assertInstanceOf(TransportCollection::class, $transportsCollection);
        $traceableTransport = current(iterator_to_array($transportsCollection->getIterator()));

        static::assertInstanceOf(Meter::class, $meter);
        static::assertIsArray($definitions);
        static::assertInstanceOf(TraceableTransport::class, $traceableTransport);

        $this->meter = $meter;
        $this->definitions = $definitions;
        $this->traceableTransport = $traceableTransport;
    }

    public function testMeterEmitsAllConfiguredEnabledMetrics(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);

        $definitions = array_filter($this->definitions, fn (array $definition) => ($definition['enabled'] ?? true) === true);

        $this->traceableTransport->reset();
        foreach ($definitions as $name => $definition) {
            $configuredMetric = new ConfiguredMetric(name: $name, value: random_int(1, 10), labels: []);
            $this->meter->emit($configuredMetric);
        }

        $transportedMetrics = $this->traceableTransport->getEmittedMetrics();
        static::assertSameSize($definitions, $transportedMetrics);
    }

    public function testNotEmittedWithFeatureFlagOff(): void
    {
        Feature::skipTestIfActive('TELEMETRY_METRICS', $this);
        $firstConfiguredMetric = array_keys($this->definitions)[0];
        static::assertIsString($firstConfiguredMetric);
        $this->traceableTransport->reset();
        $this->meter->emit(new ConfiguredMetric(name: $firstConfiguredMetric, value: 1, labels: []));
        static::assertEmpty($this->traceableTransport->getEmittedMetrics());
    }

    public function testMeterCannotEmitInConfiguredMetrics(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);
        $this->expectException(MissingMetricConfigurationException::class);
        // update the name to a non-configured metric
        $configuredMetric = new ConfiguredMetric(name: 'random-metric-that-is-not-there', value: random_int(1, 10), labels: []);
        $this->meter->emit($configuredMetric);
        $transportedMetrics = $this->traceableTransport->getEmittedMetrics();
        static::assertEmpty($transportedMetrics);
    }
}
