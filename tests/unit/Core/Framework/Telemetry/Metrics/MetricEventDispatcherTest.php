<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Extractor\MetricExtractor;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\MetricEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MetricEventDispatcher::class)]
#[Package('core')]
class MetricEventDispatcherTest extends TestCase
{
    public function testDispatchWithEvents(): void
    {
        $eventName = 'test';
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $metricExtractor = $this->createMock(MetricExtractor::class);
        $meter = $this->createMock(Meter::class);

        $counter = new Counter('test', 1);
        $event = new InvalidateCacheEvent([]);
        $metricExtractor->expects(static::once())
            ->method('fromEvent')
            ->with($event)
            ->willReturn([$counter]);
        $meter->expects(static::once())->method('emit')->with($counter);

        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with($event, $eventName)
            ->willReturn($event);

        $metricEventDispatcher = new MetricEventDispatcher($eventDispatcher, $metricExtractor, $meter);
        $result = $metricEventDispatcher->dispatch($event, $eventName);

        static::assertSame($event, $result);
    }
}
