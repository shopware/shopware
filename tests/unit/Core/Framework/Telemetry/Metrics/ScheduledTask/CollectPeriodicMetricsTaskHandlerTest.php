<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Shopware\Core\Framework\Telemetry\Metrics\ScheduledTask\CollectPeriodicMetricsTaskHandler;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CollectPeriodicMetricsTaskHandler::class)]
class CollectPeriodicMetricsTaskHandlerTest extends TestCase
{
    public function testCollectorsAreCalledAndMetricsEmitted(): void
    {
        $metric1 = new ConfiguredMetric('metric.one', 42);
        $metric2 = new ConfiguredMetric('metric.two', 100);

        $collector1 = $this->createMock(PeriodicMetricCollectorInterface::class);
        $collector1->expects($this->once())->method('collect')->willReturn([$metric1]);

        $collector2 = $this->createMock(PeriodicMetricCollectorInterface::class);
        $collector2->expects($this->once())->method('collect')->willReturn([$metric2]);

        $meter = $this->createMock(Meter::class);
        $meter->expects($this->exactly(2))
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $m) use ($metric1, $metric2): bool {
                static $callIndex = 0;
                $expected = [$metric1, $metric2];

                return $m === $expected[$callIndex++];
            }));

        $handler = new CollectPeriodicMetricsTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $meter,
            [$collector1, $collector2],
        );

        $handler->run();
    }

    public function testOneCollectorFailureDoesNotStopOthers(): void
    {
        $failingCollector = $this->createMock(PeriodicMetricCollectorInterface::class);
        $failingCollector->expects($this->once())->method('collect')->willThrowException(new \RuntimeException('DB timeout'));

        $metric = new ConfiguredMetric('metric.ok', 1);
        $workingCollector = $this->createMock(PeriodicMetricCollectorInterface::class);
        $workingCollector->expects($this->once())->method('collect')->willReturn([$metric]);

        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())->method('emit')->with($metric);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(static::stringContains('DB timeout'));

        $handler = new CollectPeriodicMetricsTaskHandler(
            $this->createMock(EntityRepository::class),
            $logger,
            $meter,
            [$failingCollector, $workingCollector],
        );

        $handler->run();
    }

    public function testGeneratorCollectorThrowingMidIterationDoesNotStopOthers(): void
    {
        $failingCollector = $this->createMock(PeriodicMetricCollectorInterface::class);
        $failingCollector->expects($this->once())->method('collect')->willReturnCallback(static function (): \Generator {
            yield new ConfiguredMetric('metric.partial', 1);

            throw new \RuntimeException('generator fail');
        });

        $metric = new ConfiguredMetric('metric.ok', 1);
        $workingCollector = $this->createMock(PeriodicMetricCollectorInterface::class);
        $workingCollector->expects($this->once())->method('collect')->willReturn([$metric]);

        $meter = $this->createMock(Meter::class);
        // The partially-yielded metric must not leak through: iterator_to_array materializes the
        // generator inside the try/catch, so the whole batch from the failing collector is discarded.
        $meter->expects($this->once())->method('emit')->with($metric);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(static::stringContains('generator fail'));

        $handler = new CollectPeriodicMetricsTaskHandler(
            $this->createMock(EntityRepository::class),
            $logger,
            $meter,
            [$failingCollector, $workingCollector],
        );

        $handler->run();
    }
}
