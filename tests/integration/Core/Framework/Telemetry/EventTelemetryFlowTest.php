<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter as CounterAttribute;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Test\Telemetry\Transport\TraceableTransport;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
class EventTelemetryFlowTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEventDispatcherMetric(): void
    {
        Feature::skipTestIfInActive('TELEMETRY_METRICS', $this);

        $transport = $this->getContainer()->get(TraceableTransport::class);
        static::assertInstanceOf(TraceableTransport::class, $transport);
        $transport->reset();
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch(new #[CounterAttribute('name', 1)] class {});
        static::assertEquals(new Counter('name', 1), $transport->getEmittedMetrics()[0]);
    }
}
