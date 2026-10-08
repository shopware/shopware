<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IndexerMetricsInstrumentor::class)]
class IndexerMetricsInstrumentorTest extends TestCase
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function testEmitsBatchSizeAndRunDurationWithResolvedLabels(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage(['a', 'b', 'c'], isFullIndexing: true),
            fn () => null,
        );

        $batchSize = $this->getMetric('indexer.batch.size');
        static::assertSame(3, $batchSize->value);
        static::assertSame(['indexer' => 'product.indexer', 'mode' => 'full'], $batchSize->labels);

        $duration = $this->getMetric('indexer.run.duration');
        static::assertIsFloat($duration->value);
        static::assertGreaterThanOrEqual(0.0, $duration->value);
        static::assertSame(['indexer' => 'product.indexer', 'mode' => 'full', 'result' => 'success'], $duration->labels);
    }

    public function testModeIsPartialWhenNotFullIndexing(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage(['a'], isFullIndexing: false),
            fn () => null,
        );

        static::assertSame('partial', $this->getMetric('indexer.batch.size')->labels['mode']);
        static::assertSame('partial', $this->getMetric('indexer.run.duration')->labels['mode']);
    }

    public function testBatchSizeIsOneForSingleNonArrayPayload(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('product.indexer'),
            $this->createMessage('single-id', isFullIndexing: true),
            fn () => null,
        );

        static::assertSame(1, $this->getMetric('indexer.batch.size')->value);
    }

    public function testIndexerNameIsPassedThroughUnmapped(): void
    {
        $this->createInstrumentor()->measureRun(
            $this->createIndexer('acme.custom.indexer'),
            $this->createMessage(['a'], isFullIndexing: true),
            fn () => null,
        );

        static::assertSame('acme.custom.indexer', $this->getMetric('indexer.run.duration')->labels['indexer']);
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
                    throw new \RuntimeException('boom');
                },
            );
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown, 'the original exception must propagate');
        static::assertSame('boom', $thrown->getMessage());

        // batch size is emitted up front, duration is still recorded on the failure path (labelled failed)
        static::assertSame(2, $this->getMetric('indexer.batch.size')->value);
        static::assertSame('failed', $this->getMetric('indexer.run.duration')->labels['result']);
    }

    private function getMetric(string $name): ConfiguredMetric
    {
        foreach ($this->emitted as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        static::fail(\sprintf('Metric "%s" was not emitted', $name));
    }

    private function createInstrumentor(): IndexerMetricsInstrumentor
    {
        $meter = static::createStub(Meter::class);
        $meter->method('emit')->willReturnCallback(function (ConfiguredMetric $metric): void {
            $this->emitted[] = $metric;
        });

        return new IndexerMetricsInstrumentor($meter);
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
