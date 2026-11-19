<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

use function PHPUnit\Framework\assertInstanceOf;

/**
 * @internal
 */
#[Package('framework')]
class MeterTest extends TestCase
{
    use KernelTestBehaviour;

    private TraceableTransport $traceableTransport;

    /**
     * @var array<string, array{type: string, description: string, enabled?: bool, parameters: array<mixed>, labels: array<mixed>, unit?: string}>
     */
    private array $definitions;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();
        $meter = static::getContainer()->get(Meter::class);
        $definitions = static::getContainer()->getParameter('shopware.telemetry.metrics.definitions');
        $transportsCollection = static::getContainer()->get(TransportCollection::class);
        assertInstanceOf(TransportCollection::class, $transportsCollection);
        $traceableTransport = current(iterator_to_array($transportsCollection->getIterator()));

        static::assertInstanceOf(Meter::class, $meter);
        static::assertIsArray($definitions);
        static::assertInstanceOf(TraceableTransport::class, $traceableTransport);

        $this->meter = $meter;
        $this->definitions = $definitions;
        $this->traceableTransport = $traceableTransport;
    }

    public function testNotEmittedWithFeatureFlagOff(): void
    {
        Feature::skipTestIfActive('TELEMETRY_METRICS', $this);
        $firstConfiguredMetric = array_keys($this->definitions)[0];
        static::assertIsString($firstConfiguredMetric);
        $this->traceableTransport->reset();
        $this->meter->emit(new ConfiguredMetric(name: $firstConfiguredMetric, value: 1, labels: []));
        static::assertCount(0, $this->traceableTransport->getEmittedMetrics());
    }
}
