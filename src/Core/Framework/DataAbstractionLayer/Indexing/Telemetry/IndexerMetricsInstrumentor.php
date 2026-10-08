<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry}:
 * derives the indexer run metrics (duration, batch size) from a single `EntityIndexer::handle()` call,
 * keeping telemetry out of the registry.
 *
 * Times manually on `Meter` rather than via `Telemetry::instrument()`: `run.duration` carries a `result`
 * label that is only known once `handle()` returns or throws, so labels can't be fixed up-front.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled, no compiler-pass gating.
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
    public const INDEXING_SUCCESS = 'success';
    public const INDEXING_FAILED = 'failed';
    private const INDEXING_MODE_FULL = 'full';
    private const INDEXING_MODE_PARTIAL = 'partial';

    public function __construct(private readonly Meter $meter)
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

        $this->meter->emit(new ConfiguredMetric(
            name: 'indexer.batch.size',
            value: \is_array($message->getData()) ? \count($message->getData()) : 1,
            labels: ['indexer' => $indexerName, 'mode' => $mode],
        ));

        $result = self::INDEXING_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            $callback();
        } catch (\Throwable $e) {
            $result = self::INDEXING_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'indexer.run.duration',
                value: $timer->getElapsedMs(),
                labels: ['indexer' => $indexerName, 'mode' => $mode, 'result' => $result],
            ));
        }
    }
}
