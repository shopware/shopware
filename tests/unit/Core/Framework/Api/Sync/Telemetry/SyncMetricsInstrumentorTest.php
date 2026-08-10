<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SyncMetricsInstrumentor::class)]
class SyncMetricsInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testEmitsOperationsCountWithNumberOfOperations(): void
    {
        $operations = [
            $this->operation('product'),
            $this->operation('order'),
        ];

        $this->createInstrumentor()->measure(
            $operations,
            new SyncBehavior(),
            fn (): SyncResult => new SyncResult([]),
        );

        $count = $this->getMetric('api.sync.operations.count');
        static::assertInstanceOf(ConfiguredMetric::class, $count);
        static::assertSame(2, $count->value);
        static::assertSame([], $count->labels);
    }

    public function testDurationUsesDefaultIndexingBehaviorAndSuccessResult(): void
    {
        $this->createInstrumentor()->measure(
            [],
            new SyncBehavior(),
            fn (): SyncResult => new SyncResult([]),
        );

        $duration = $this->getMetric('api.sync.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame('default', $duration->labels['indexing_behavior']);
        static::assertSame('success', $duration->labels['result']);
    }

    public function testDurationPassesThroughExplicitIndexingBehavior(): void
    {
        $this->createInstrumentor()->measure(
            [],
            new SyncBehavior('use-queue-indexing'),
            fn (): SyncResult => new SyncResult([]),
        );

        $duration = $this->getMetric('api.sync.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('use-queue-indexing', $duration->labels['indexing_behavior']);
    }

    public function testEmitsAffectedEntitiesAggregatedPerGroupAndAction(): void
    {
        $result = new SyncResult(
            ['product' => ['pk-1', 'pk-2'], 'product_price' => ['pk-3']],
            [],
            ['order' => ['pk-4']],
        );

        $this->createInstrumentor()->measure(
            [],
            new SyncBehavior(),
            fn (): SyncResult => $result,
        );

        $affected = $this->findMetrics('api.sync.entities.affected');
        static::assertCount(2, $affected);

        $upsert = $this->getAffected('product', 'upsert');
        static::assertInstanceOf(ConfiguredMetric::class, $upsert);
        // product + product_price both bucket to the product group → 2 + 1 summed
        static::assertSame(3, $upsert->value);

        $delete = $this->getAffected('order', 'delete');
        static::assertInstanceOf(ConfiguredMetric::class, $delete);
        static::assertSame(1, $delete->value);
    }

    public function testUnknownEntityNameResolvesToOtherGroup(): void
    {
        $result = new SyncResult(['totally_unknown' => ['pk-1']]);

        $this->createInstrumentor()->measure(
            [],
            new SyncBehavior(),
            fn (): SyncResult => $result,
        );

        $upsert = $this->getAffected('other', 'upsert');
        static::assertInstanceOf(ConfiguredMetric::class, $upsert);
        static::assertSame(1, $upsert->value);
    }

    public function testThrowingCallbackIsRethrownDurationFailedAndNoAffectedEntities(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measure(
                [$this->operation('product')],
                new SyncBehavior(),
                function (): SyncResult {
                    throw new \RuntimeException('boom');
                },
            );
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('boom', $thrown->getMessage());

        $duration = $this->getMetric('api.sync.duration');
        static::assertInstanceOf(ConfiguredMetric::class, $duration);
        static::assertSame('failed', $duration->labels['result']);
        static::assertSame([], $this->findMetrics('api.sync.entities.affected'));
    }

    public function testMeasureReturnsSyncResultFromCallback(): void
    {
        $result = new SyncResult([]);

        $returned = $this->createInstrumentor()->measure(
            [],
            new SyncBehavior(),
            fn (): SyncResult => $result,
        );

        static::assertSame($result, $returned);
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

    /**
     * @return list<ConfiguredMetric>
     */
    private function findMetrics(string $name): array
    {
        return \array_values(\array_filter($this->emitted, fn (ConfiguredMetric $metric): bool => $metric->name === $name));
    }

    private function getAffected(string $group, string $action): ?ConfiguredMetric
    {
        foreach ($this->findMetrics('api.sync.entities.affected') as $metric) {
            if ($metric->labels['entity_group'] === $group && $metric->labels['action'] === $action) {
                return $metric;
            }
        }

        return null;
    }

    private function createInstrumentor(): SyncMetricsInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        return new SyncMetricsInstrumentor($meter, new EntityGroupResolver());
    }

    private function operation(string $entity): SyncOperation
    {
        return new SyncOperation('key', $entity, SyncOperation::ACTION_UPSERT, [['id' => 'x']]);
    }
}
