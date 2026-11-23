<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\Test\Stub\Telemetry\CollectingMeter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexerMetricsInstrumentor::class)]
class IndexerMetricsInstrumentorTest extends TestCase
{
    private CollectingMeter $meter;

    public function testEmitsBatchSizeAndRunDurationWithResolvedLabels(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage(['a', 'b', 'c'], isFullIndexing: true),
            fn () => null,
        );

        $batchSize = $this->meter->getMetric('indexer.batch.size');
        static::assertSame(3, $batchSize->value);
        static::assertSame(['indexer' => 'product.indexer', 'mode' => 'full'], $batchSize->labels);

        $duration = $this->meter->getMetric('indexer.run.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['indexer' => 'product.indexer', 'mode' => 'full', 'result' => 'success'], $this->meter->getLabels('indexer.run.duration'));
    }

    public function testModeIsPartialWhenNotFullIndexing(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage(['a'], isFullIndexing: false),
            fn () => null,
        );

        $labels = $this->meter->getLabels('indexer.batch.size');
        static::assertSame('partial', $labels['mode']);
        $labels = $this->meter->getLabels('indexer.run.duration');
        static::assertSame('partial', $labels['mode']);
    }

    public function testBatchSizeIsOneForSingleNonArrayPayload(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage('single-id', isFullIndexing: true),
            fn () => null,
        );

        static::assertSame(1, $this->meter->getMetric('indexer.batch.size')->value);
    }

    public function testIndexerNameIsPassedThroughUnmapped(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('acme.custom.indexer'),
            $this->createMessage(['a'], isFullIndexing: true),
            fn () => null,
        );

        $labels = $this->meter->getLabels('indexer.run.duration');
        static::assertSame('acme.custom.indexer', $labels['indexer']);
    }

    public function testCallbackIsInvokedExactlyOnce(): void
    {
        $calls = 0;

        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage(['a'], isFullIndexing: true),
            function () use (&$calls): void {
                ++$calls;
            },
        );

        static::assertSame(1, $calls);
    }

    public function testFailingCallbackIsRethrownAndDurationRecordedAsFailed(): void
    {
        $thrown = null;

        try {
            $this->createInstrumentor()->measureRun(
                $this->createIndexer('product.indexer'),
                $this->createMessage(['a', 'b'], isFullIndexing: true),
                function (): void {
                    throw new \RuntimeException('indexing failed');
                },
            );
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('indexing failed', $thrown->getMessage());

        // batch size is emitted up front, duration is still recorded on the failure path (labelled failed)
        static::assertSame(2, $this->meter->getMetric('indexer.batch.size')->value);
        $labels = $this->meter->getLabels('indexer.run.duration');
        static::assertSame('failed', $labels['result']);
    }

    private function createInstrumentor(): IndexerMetricsInstrumentor
    {
        $this->meter = new CollectingMeter();

        return new IndexerMetricsInstrumentor(new Telemetry($this->meter, 'test'));
    }

    private function createIndexer(string $name): EntityIndexer&Stub
    {
        $indexer = static::createStub(EntityIndexer::class);
        $indexer->method('getName')->willReturn($name);

        return $indexer;
    }

    /**
     * @param array<string>|string $data
     */
    private function createMessage(array|string $data, bool $isFullIndexing): EntityIndexingMessage
    {
        return new EntityIndexingMessage($data, null, null, false, $isFullIndexing);
    }
}
