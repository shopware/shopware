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
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SyncMetricsInstrumentor::class)]
class SyncMetricsInstrumentorTest extends TestCase
{
    private CollectingMeter $meter;

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

        $count = $this->meter->getMetric('api.sync.operations.count');
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

        $duration = $this->meter->getMetric('api.sync.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        $labels = $duration->labels;
        static::assertIsArray($labels);
        static::assertSame('default', $labels['indexing_behavior']);
        static::assertSame('success', $labels['result']);
    }

    public function testDurationPassesThroughExplicitIndexingBehavior(): void
    {
        $this->createInstrumentor()->measure(
            [],
            new SyncBehavior('use-queue-indexing'),
            fn (): SyncResult => new SyncResult([]),
        );

        $duration = $this->meter->getMetric('api.sync.duration');
        $labels = $duration->labels;
        static::assertIsArray($labels);
        static::assertSame('use-queue-indexing', $labels['indexing_behavior']);
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

        $affected = $this->meter->getMetrics('api.sync.entities.affected');
        static::assertCount(2, $affected);

        $upsert = $this->getAffected('product', 'upsert');
        // product + product_price both bucket to the product group → 2 + 1 summed
        static::assertSame(3, $upsert->value);

        $delete = $this->getAffected('order', 'delete');
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

        $duration = $this->meter->getMetric('api.sync.duration');
        $labels = $duration->labels;
        static::assertIsArray($labels);
        static::assertSame('failed', $labels['result']);
        static::assertSame([], $this->meter->getMetrics('api.sync.entities.affected'));
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

    private function getAffected(string $group, string $action): ConfiguredMetric
    {
        foreach ($this->meter->getMetrics('api.sync.entities.affected') as $metric) {
            $labels = $metric->labels;
            static::assertIsArray($labels);
            if ($labels['entity_group'] === $group && $labels['action'] === $action) {
                return $metric;
            }
        }

        static::fail(\sprintf('No api.sync.entities.affected metric for group "%s" and action "%s"', $group, $action));
    }

    private function createInstrumentor(): SyncMetricsInstrumentor
    {
        $this->meter = new CollectingMeter();

        return new SyncMetricsInstrumentor($this->meter, new EntityGroupResolver());
    }

    private function operation(string $entity): SyncOperation
    {
        return new SyncOperation('key', $entity, SyncOperation::ACTION_UPSERT, [['id' => 'x']]);
    }
}
