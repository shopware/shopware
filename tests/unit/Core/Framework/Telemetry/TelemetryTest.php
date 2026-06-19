<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopware\Core\Framework\Telemetry\Instrumentation\Span;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Shopware\Core\Profiling\Integration\ProfilerInterface;
use Shopware\Core\Profiling\Profiler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Telemetry::class)]
class TelemetryTest extends TestCase
{
    protected function setUp(): void
    {
        new Profiler(new \ArrayIterator([]), []);
    }

    public function testEmitDelegatesToMeter(): void
    {
        $configured = new ConfiguredMetric('order.placed.count', 1, ['channel' => 'web']);

        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())->method('emit')->with($configured);

        $telemetry = new Telemetry($meter, 'prod');
        $telemetry->emit($configured);
    }

    public function testInstrumentEmits(): void
    {
        $emitted = null;
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->willReturnCallback(static function (ConfiguredMetric $metric) use (&$emitted): void {
                $emitted = $metric;
            });

        $telemetry = new Telemetry($meter, 'prod');
        $result = $telemetry->instrument(
            callback: static function (): string {
                return 'result';
            },
            metric: new DurationMetric('op.duration', ['method' => 'card']),
        );

        static::assertNotNull($emitted);
        static::assertSame('op.duration', $emitted->name);
        static::assertSame(['method' => 'card'], $emitted->labels);
        static::assertIsFloat($emitted->value);
        static::assertGreaterThan(0.0, $emitted->value);
        static::assertSame('result', $result);
    }

    public function testInstrumentEmitsWhenCallbackThrows(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())->method('emit');

        $telemetry = new Telemetry($meter, 'prod');

        $this->expectException(\RuntimeException::class);

        $telemetry->instrument(
            callback: static fn () => throw new \RuntimeException('boom'),
            metric: new DurationMetric('op.duration'),
        );
    }

    public function testInstrumentWithSpanOnlyDoesNotEmitMetric(): void
    {
        $profilerMock = $this->createMock(ProfilerInterface::class);
        $profilerMock->expects($this->once())->method('start')->with('cache-warmup', 'cache', ['t' => 'v']);
        $profilerMock->expects($this->once())->method('stop')->with('cache-warmup');
        new Profiler(new \ArrayIterator(['p' => $profilerMock]), ['p']);

        $meter = $this->createMock(Meter::class);
        $meter->expects($this->never())->method('emit');

        $telemetry = new Telemetry($meter, 'prod');
        $result = $telemetry->instrument(
            callback: static fn () => 'done',
            span: new Span('cache-warmup', category: 'cache', tags: ['t' => 'v']),
        );

        static::assertSame('done', $result);
    }

    public function testInstrumentWithSpanAndMetricEmitsAfterSpanStops(): void
    {
        $events = [];

        $profilerMock = $this->createMock(ProfilerInterface::class);
        $profilerMock->method('start')->willReturnCallback(static function () use (&$events): void {
            $events[] = 'span-start';
        });
        $profilerMock->method('stop')->willReturnCallback(static function () use (&$events): void {
            $events[] = 'span-stop';
        });
        new Profiler(new \ArrayIterator(['p' => $profilerMock]), ['p']);

        $meter = $this->createMock(Meter::class);
        $meter->method('emit')->willReturnCallback(static function () use (&$events): void {
            $events[] = 'metric-emit';
        });

        $telemetry = new Telemetry($meter, 'prod');
        $telemetry->instrument(
            callback: static function () use (&$events): void {
                $events[] = 'callback';
            },
            metric: new DurationMetric('op.duration'),
            span: new Span('op'),
        );

        static::assertSame(['span-start', 'callback', 'span-stop', 'metric-emit'], $events);
    }

    public function testInstrumentWithoutMetricOrSpanThrowsInDev(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->never())->method('emit');

        $telemetry = new Telemetry($meter, 'dev');

        $this->expectException(TelemetryException::class);

        $telemetry->instrument(callback: static fn () => 'unused');
    }

    public function testInstrumentWithoutMetricOrSpanRunsCallbackInProd(): void
    {
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->never())->method('emit');

        $telemetry = new Telemetry($meter, 'prod');
        $result = $telemetry->instrument(callback: static fn () => 'ran');

        static::assertSame('ran', $result);
    }
}
