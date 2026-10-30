<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CollectingMeter::class)]
class CollectingMeterTest extends TestCase
{
    private CollectingMeter $meter;

    protected function setUp(): void
    {
        $this->meter = new CollectingMeter();
    }

    public function testEveryMetricCountsAsEnabled(): void
    {
        static::assertTrue($this->meter->isEnabled('any.metric'));
    }

    public function testGetMetricsReturnsAllEmittedMetricsInOrder(): void
    {
        $first = new ConfiguredMetric('cart.first.count', 1);
        $second = new ConfiguredMetric('cart.second.count', 2);

        $this->meter->emit($first);
        $this->meter->emit($second);

        static::assertSame([$first, $second], $this->meter->getMetrics());
    }

    public function testGetMetricsFiltersByName(): void
    {
        $matching = new ConfiguredMetric('cart.errors.count', 1);
        $other = new ConfiguredMetric('cart.line_items.count', 5);

        $this->meter->emit($matching);
        $this->meter->emit($other);
        $this->meter->emit($matching);

        static::assertSame([$matching, $matching], $this->meter->getMetrics('cart.errors.count'));
    }

    public function testGetMetricReturnsTheSingleMetricWithTheName(): void
    {
        $metric = new ConfiguredMetric('cart.errors.count', 1);

        $this->meter->emit(new ConfiguredMetric('cart.line_items.count', 5));
        $this->meter->emit($metric);

        static::assertSame($metric, $this->meter->getMetric('cart.errors.count'));
    }

    public function testGetMetricThrowsWhenTheMetricWasNotEmitted(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Metric "cart.errors.count" was not emitted'));

        $this->meter->getMetric('cart.errors.count');
    }

    public function testGetMetricThrowsWhenTheMetricWasEmittedMoreThanOnce(): void
    {
        $this->meter->emit(new ConfiguredMetric('cart.errors.count', 1));
        $this->meter->emit(new ConfiguredMetric('cart.errors.count', 2));

        $this->expectExceptionObject(new \RuntimeException('Metric "cart.errors.count" was emitted 2 times, expected once'));

        $this->meter->getMetric('cart.errors.count');
    }

    public function testGetMetricNamesListsEveryEmissionInOrder(): void
    {
        $this->meter->emit(new ConfiguredMetric('cart.errors.count', 1));
        $this->meter->emit(new ConfiguredMetric('cart.line_items.count', 5));
        $this->meter->emit(new ConfiguredMetric('cart.errors.count', 2));

        static::assertSame(
            ['cart.errors.count', 'cart.line_items.count', 'cart.errors.count'],
            $this->meter->getMetricNames(),
        );
    }

    public function testGetLabelsReturnsPlainLabelsAsIs(): void
    {
        $this->meter->emit(new ConfiguredMetric('cart.errors.count', 1, ['sales_channel_type' => 'storefront']));

        static::assertSame(['sales_channel_type' => 'storefront'], $this->meter->getLabels('cart.errors.count'));
    }

    public function testGetLabelsResolvesLabelClosuresLikeTheMeter(): void
    {
        $this->meter->emit(new ConfiguredMetric(
            'cart.calculation.duration',
            12.5,
            static fn (): array => ['result' => 'success'],
        ));

        static::assertSame(['result' => 'success'], $this->meter->getLabels('cart.calculation.duration'));
    }
}
