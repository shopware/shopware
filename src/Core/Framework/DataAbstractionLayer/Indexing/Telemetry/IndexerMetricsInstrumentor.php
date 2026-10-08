<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopware\Core\Framework\Telemetry\Instrumentation\OperationResult;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry}:
 * derives the indexer run metrics (duration, batch size) from a single `EntityIndexer::handle()` call,
 * keeping telemetry out of the registry.
 *
 * Merely-hot path: relies on the Meter's early-return when telemetry is disabled, no compiler-pass gating.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class IndexerMetricsInstrumentor
{
    private const INDEXING_MODE_FULL = 'full';
    private const INDEXING_MODE_PARTIAL = 'partial';

    public function __construct(private readonly Telemetry $telemetry)
    {
    }

    /**
     * Runs a single `EntityIndexer::handle()`, recording its batch size and timed duration.
     * Indexer exceptions propagate out of `handle()`; the duration is still emitted (labelled
     * `result=failed`) so slow failures stay visible instead of skewing the success distribution.
     *
     * @param \Closure(): void $callback
     */
    public function measureRun(EntityIndexer $indexer, EntityIndexingMessage $message, \Closure $callback): void
    {
        $indexerName = $indexer->getName();
        $mode = $message->isFullIndexing ? self::INDEXING_MODE_FULL : self::INDEXING_MODE_PARTIAL;

        $this->telemetry->emit(new ConfiguredMetric(
            name: 'indexer.batch.size',
            value: \is_array($message->getData()) ? \count($message->getData()) : 1,
            labels: ['indexer' => $indexerName, 'mode' => $mode],
        ));

        $this->telemetry->instrument($callback, new DurationMetric(
            name: 'indexer.run.duration',
            labels: static fn (mixed $result, ?\Throwable $e): array => [
                'indexer' => $indexerName,
                'mode' => $mode,
                'result' => OperationResult::fromOutcome($e),
            ],
        ));
    }
}
