<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Extractor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter as CounterAttribute;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Gauge as GaugeAttribute;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\Histogram as HistogramAttribute;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\UpDownCounter as UpDownCounterAttribute;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Extractor\MetricExtractor;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter;

/**
 * @internal
 */
#[CoversClass(MetricExtractor::class)]
class MetricExtractorTest extends TestCase
{
    /**
     * @param array<Counter|Gauge|Histogram|UpDownCounter> $expectedMetrics
     */
    #[DataProvider('provideDataClasses')]
    public function testEventsHasMetrics(object $event, array $expectedMetrics): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $metricExtractor = new MetricExtractor($logger);
        $metrics = $metricExtractor->fromEvent($event);
        static::assertEquals($expectedMetrics, $metrics);
    }

    public function testEventHasDynamicProperty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $metricExtractor = new MetricExtractor($logger);
        $event = new #[CounterAttribute(name: 'metric1', value: 'metricValue', type: CounterAttribute::TYPE_DYNAMIC)] class {
            public int $metricValue = 1233;
        };

        $metrics = $metricExtractor->fromEvent($event);
        static::assertEquals(new Counter('metric1', $event->metricValue), $metrics[0]);
    }

    public function testEventHasPropertyMethod(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $metricExtractor = new MetricExtractor($logger);
        $event = new #[CounterAttribute(name: 'metric1', value: 'metricValue', type: CounterAttribute::TYPE_DYNAMIC)] class {
            public function metricValue(): int
            {
                return 1233;
            }
        };

        $metrics = $metricExtractor->fromEvent($event);
        static::assertEquals(new Counter('metric1', $event->metricValue()), $metrics[0]);
    }

    public function testLogsInvalidPropertyName(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('error')
            ->with(
                'Failed to extract metric from the attribute Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter of event',
                static::callback(function (array $context) {
                    $exception = $context['exception'];
                    static::assertInstanceOf(InvalidMetricValueException::class, $exception);
                    static::assertSame(
                        'Invalid value type NULL retrieved from the attribute Shopware\Core\Framework\Telemetry\Metrics\Attribute\Counter for the metric the_exceptional_metric',
                        $exception->getMessage()
                    );

                    return true;
                })
            );

        $metricExtractor = new MetricExtractor($logger);
        $event = new #[CounterAttribute(name: 'the_exceptional_metric', value: 'bla', type: CounterAttribute::TYPE_DYNAMIC)] class {};

        $metrics = $metricExtractor->fromEvent($event);
    }

    /**
     * @return array<array{object, array<Counter|Gauge|Histogram|UpDownCounter>}>
     */
    public static function provideDataClasses(): array
    {
        return [
            'event class with no metrics' => [
                new class {},
                [],
            ],
            'event class with one metric' => [
                new #[CounterAttribute('metric1', 1)] class {},
                [
                    new Counter('metric1', 1),
                ],
            ],
            'event class with different metrics' => [
                new #[CounterAttribute('metric1', 10), GaugeAttribute('metric2', 20), HistogramAttribute('metric3', 1), UpDownCounterAttribute('metric4', -1)] class {},
                [
                    new Counter('metric1', 10),
                    new Gauge('metric2', 20),
                    new Histogram('metric3', 1),
                    new UpDownCounter('metric4', -1),
                ],
            ],
        ];
    }
}
