<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\System\NumberRange\Telemetry\IncrementStorageMetricsDecorator;
use Shopware\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IncrementStorageMetricsDecorator::class)]
class IncrementStorageMetricsDecoratorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testReserveReturnsDecoratedValueAndEmitsDurationWithResolvedLabels(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $decorated->method('reserve')->willReturn(42);

        $result = $this->createDecorator($decorated, 'mysql')->reserve($this->config('order'));

        static::assertSame(42, $result);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(
            [
                'number_range_type' => 'number_range_type_label:order',
                'storage' => 'mysql',
                'result' => 'success',
            ],
            $duration->labels,
        );
    }

    public function testMissingTechnicalNameForwardsNullToResolver(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $decorated->method('reserve')->willReturn(1);

        // creating config with missing technical_name
        $config = $this->config(null);

        $this->createDecorator($decorated, 'mysql')->reserve($config);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('number_range_type_label:', $duration->labels['number_range_type']);
    }

    public function testFailingReserveIsRethrownAndDurationRecordedAsFailed(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);
        $exception = new \RuntimeException('lock wait timeout');
        $decorated->method('reserve')->willThrowException($exception);

        $thrown = null;

        try {
            $this->createDecorator($decorated, 'mysql')->reserve($this->config('order'));
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame($exception, $thrown);

        $duration = $this->getMetric('number_range.allocation.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('failed', $duration->labels['result']);
        static::assertSame('number_range_type_label:order', $duration->labels['number_range_type']);
        static::assertSame('mysql', $duration->labels['storage']);
    }

    public function testPreviewDelegatesAndEmitsNothing(): void
    {
        $config = $this->config('order');

        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('preview')
            ->with($config)
            ->willReturn(7);

        static::assertSame(7, $this->createDecorator($decorated, 'mysql')->preview($config));
        static::assertSame([], $this->emitted);
    }

    public function testListDelegatesAndEmitsNothing(): void
    {
        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('list')
            ->willReturn(['config-id' => 5]);

        static::assertSame(['config-id' => 5], $this->createDecorator($decorated, 'mysql')->list());
        static::assertSame([], $this->emitted);
    }

    public function testSetDelegatesAndEmitsNothing(): void
    {
        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('set')
            ->with('config-id', 99);

        $this->createDecorator($decorated, 'mysql')->set('config-id', 99);

        static::assertSame([], $this->emitted);
    }

    public function testIncreaseToAtLeastDelegatesAndEmitsNothing(): void
    {
        $decorated = $this->createMock(AbstractIncrementStorage::class);
        $decorated->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('config-id', 99);

        $this->createDecorator($decorated, 'mysql')->increaseToAtLeast('config-id', 99);

        static::assertSame([], $this->emitted);
    }

    public function testGetDecoratedReturnsDecoratedInstance(): void
    {
        $decorated = static::createStub(AbstractIncrementStorage::class);

        static::assertSame($decorated, $this->createDecorator($decorated, 'mysql')->getDecorated());
    }

    private function createDecorator(AbstractIncrementStorage $decorated, string $storage): IncrementStorageMetricsDecorator
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        // Pass-through resolver stub: echoes the technical name back with a fixed prefix, so it's easy to validate
        $typeResolver = static::createStub(NumberRangeTypeResolver::class);
        $typeResolver->method('resolve')->willReturnCallback(
            static fn (?string $technicalName): string => 'number_range_type_label:' . $technicalName
        );

        return new IncrementStorageMetricsDecorator($decorated, $meter, $typeResolver, $storage);
    }

    /**
     * @return array{id: string, pattern: string, start: ?int, technical_name?: string}
     */
    private function config(?string $technicalName): array
    {
        $config = [
            'id' => 'config-id',
            'pattern' => '{n}',
            'start' => 1,
        ];

        if ($technicalName !== null) {
            $config['technical_name'] = $technicalName;
        }

        return $config;
    }

    private function getMetric(string $name): ?ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }
}
