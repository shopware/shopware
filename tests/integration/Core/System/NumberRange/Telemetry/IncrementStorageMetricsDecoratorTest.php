<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\NumberRange\Telemetry\IncrementStorageMetricsDecorator;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * Proves the two things the unit test cannot: that the metrics decorator is actually wired around the
 * configured increment storage, and that a real allocation through {@see \Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator} emits
 * number_range.allocation.duration` all the way to a transport with the real resolver output.
 *
 * @internal
 */
#[Package('framework')]
class IncrementStorageMetricsDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testActiveIncrementStorageIsDecorated(): void
    {
        $storage = static::getContainer()->get(AbstractIncrementStorage::class);

        static::assertInstanceOf(IncrementStorageMetricsDecorator::class, $storage);
        static::assertNotInstanceOf(IncrementStorageMetricsDecorator::class, $storage->getDecorated());
    }

    public function testReservingAnOrderNumberEmitsDurationMetric(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);

        $transport = $this->getTraceableTransport();
        $transport->reset();

        static::getContainer()->get(AbstractNumberRangeValueGenerator::class)
            ->getValue('order', Context::createDefaultContext(), Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $metric = $this->findMetric($transport, 'number_range.allocation.duration');

        static::assertNotNull($metric, 'number_range.allocation.duration was not emitted for a real order allocation');
        // monotonic-clock duration, not freezable — assert it is a sane non-negative value, not a fixed number
        static::assertGreaterThanOrEqual(0.0, $metric->value);
        static::assertSame('order', $metric->labels['number_range_type']);
        static::assertSame('success', $metric->labels['result']);
        static::assertContains($metric->labels['storage'], ['mysql', 'redis']);
    }

    private function getTraceableTransport(): TraceableTransport
    {
        // TransportCollection is a singleton, so this is the exact instance the Meter — and therefore the
        // decorator under test — emits through, not a copy. In the test env it holds only the
        // TraceableTransport (see config/services_test.php); we still look it up by type rather than by
        // position so the intent is explicit and a future second transport can't be picked by accident.
        $transports = static::getContainer()->get(TransportCollection::class);
        static::assertInstanceOf(TransportCollection::class, $transports);

        foreach ($transports as $transport) {
            if ($transport instanceof TraceableTransport) {
                return $transport;
            }
        }

        static::fail('No TraceableTransport is configured in the telemetry TransportCollection');
    }

    private function findMetric(TraceableTransport $transport, string $name): ?Metric
    {
        foreach ($transport->getEmittedMetrics() as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }
}
