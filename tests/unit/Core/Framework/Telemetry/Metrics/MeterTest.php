<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
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
        $metric = $this->createMock(MetricInterface::class);
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects(static::once())->method('emit')->with($metric);
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects(static::once())->method('emit')->with($metric);

        $meter = new Meter(new \ArrayIterator([$transport1, $transport2]), $this->createMock(LoggerInterface::class));
        $meter->emit($metric);
    }

    public function testTransportErrorDoesNotBreakApplication(): void
    {
        $metric = $this->createMock(MetricInterface::class);
        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport1->expects(static::once())->method('emit')->with($metric)->willThrowException(new \RuntimeException('Transport failed'));
        $transport2 = $this->createMock(MetricTransportInterface::class);
        $transport2->expects(static::once())->method('emit')->with($metric);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $meter = new Meter(new \ArrayIterator([$transport1, $transport2]), $logger);
        $meter->emit($metric);
    }

    public function testMetricNotSupportedException(): void
    {
        $metric = $this->createMock(MetricInterface::class);
        $transport = $this->createMock(MetricTransportInterface::class);
        $transport->expects(static::once())
            ->method('emit')
            ->with($metric)
            ->willThrowException(TelemetryException::metricNotSupported($metric, $transport));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('error')
            ->with(
                static::stringContains('Metric'),
                static::arrayHasKey('exception')
            );

        $meter = new Meter(new \ArrayIterator([$transport]), $logger);
        $meter->emit($metric);
    }
}
