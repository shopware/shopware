<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricLabelProcessor;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MetricLabelProcessor::class)]
class MetricLabelProcessorTest extends TestCase
{
    public function testUnknownLabelNameThrowsInDev(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'dev');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], []);

        $this->expectExceptionObject(TelemetryException::unknownMetricLabel('test', 'unknown'));

        $processor->process($config, ['unknown' => 'value']);
    }

    public function testUnknownLabelNameThrowsInTest(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'test');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], []);

        $this->expectExceptionObject(TelemetryException::unknownMetricLabel('test', 'unknown'));

        $processor->process($config, ['unknown' => 'value']);
    }

    public function testUnknownLabelNameLogsInProd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(static::stringContains('Unknown label'));

        $processor = new MetricLabelProcessor('other', $logger, 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], []);

        $result = $processor->process($config, ['unknown' => 'value']);

        static::assertSame([], $result);
    }

    public function testOpenPolicyPassesThroughAnyValue(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], [
            'status' => new LabelConfig(policy: LabelPolicy::OPEN),
        ]);

        $result = $processor->process($config, ['status' => 'anything']);

        static::assertSame(['status' => 'anything'], $result);
    }

    public function testAllowedValuePassesThrough(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], [
            'method' => new LabelConfig(allowedValues: ['GET', 'POST']),
        ]);

        $result = $processor->process($config, ['method' => 'GET']);

        static::assertSame(['method' => 'GET'], $result);
    }

    public function testUnknownValueWithReplacePolicyReplacesValue(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], [
            'method' => new LabelConfig(allowedValues: ['GET', 'POST'], policy: LabelPolicy::REPLACE),
        ]);

        $result = $processor->process($config, ['method' => 'PATCH']);

        static::assertSame(['method' => 'other'], $result);
    }

    public function testUnknownValueWithDiscardPolicyReturnsNull(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::GAUGE, true, [], [
            'region' => new LabelConfig(allowedValues: ['eu', 'us'], policy: LabelPolicy::DISCARD),
        ]);

        $result = $processor->process($config, ['region' => 'asia']);

        static::assertNull($result);
    }

    #[DataProvider('additiveTypeProvider')]
    public function testDefaultPolicyForAdditiveTypesIsReplace(Type $type): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', $type, true, [], [
            'method' => new LabelConfig(allowedValues: ['GET']),
        ]);

        $result = $processor->process($config, ['method' => 'UNKNOWN']);

        static::assertSame(['method' => 'other'], $result);
    }

    /**
     * @return iterable<string, array{Type}>
     */
    public static function additiveTypeProvider(): iterable
    {
        yield 'counter' => [Type::COUNTER];
        yield 'histogram' => [Type::HISTOGRAM];
        yield 'updown_counter' => [Type::UPDOWN_COUNTER];
    }

    public function testDefaultPolicyForGaugeIsDiscard(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::GAUGE, true, [], [
            'region' => new LabelConfig(allowedValues: ['eu']),
        ]);

        $result = $processor->process($config, ['region' => 'asia']);

        static::assertNull($result);
    }

    public function testMultipleLabelsProcessedCorrectly(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], [
            'method' => new LabelConfig(allowedValues: ['GET', 'POST']),
            'status' => new LabelConfig(policy: LabelPolicy::OPEN),
        ]);

        $result = $processor->process($config, ['method' => 'GET', 'status' => '200']);

        static::assertSame(['method' => 'GET', 'status' => '200'], $result);
    }

    public function testEmptyLabelsReturnsEmptyArray(): void
    {
        $processor = new MetricLabelProcessor('other', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true);

        $result = $processor->process($config, []);

        static::assertSame([], $result);
    }

    public function testCustomReplacementValue(): void
    {
        $processor = new MetricLabelProcessor('_unknown_', $this->createMock(LoggerInterface::class), 'prod');
        $config = new MetricConfig('test', 'desc', Type::COUNTER, true, [], [
            'method' => new LabelConfig(allowedValues: ['GET']),
        ]);

        $result = $processor->process($config, ['method' => 'PATCH']);

        static::assertSame(['method' => '_unknown_'], $result);
    }
}
