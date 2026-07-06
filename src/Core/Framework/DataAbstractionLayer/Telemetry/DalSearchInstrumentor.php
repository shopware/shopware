<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Shopware\Core\Profiling\Profiler;

/**
 * Owns both the profiling and the `dal.search.duration` metrics for DAL read/search/aggregate operations.
 *
 * The DAL read path is extremely hot, so the metric is guarded by a single flag resolved once in the constructor:
 * `global telemetry enabled` AND `instrumentor metrics are enabled`. When it is off, `measure()`
 * degrades to the profiler span alone.
 *
 * When enabled, span and metric emit are separated, mirroring {@see \Shopware\Core\Framework\Telemetry\Telemetry::instrument()}
 *
 * Note that `EntityRepository::_search()` internally issues its own `searchIds`/`aggregate` calls, as result
 * those are emitting additional metrics.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class DalSearchInstrumentor
{
    public const OPERATION_SEARCH = 'search';
    public const OPERATION_SEARCH_IDS = 'searchIds';
    public const OPERATION_AGGREGATE = 'aggregate';

    private const BACKEND_SQL = 'sql';

    private const BACKEND_ELASTICSEARCH = 'elasticsearch';

    private const DURATION_METRIC = 'dal.search.duration';

    private const PROFILER_CATEGORY = 'repository';

    /**
     * State the Elasticsearch bundle adds to a result it served, see
     * {@see \Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher::RESULT_STATE}
     * and its aggregator counterpart. Duplicated as a literal so Core keeps no dependency on the ES bundle;
     * absence of the state (including when the bundle is inactive) means the query was served by SQL.
     */
    private const ELASTICSEARCH_RESULT_STATE = 'loaded-by-elastic';

    private readonly bool $enabled;

    public function __construct(
        private readonly Meter $meter,
        private readonly EntityGroupResolver $entityGroupResolver,
        MetricConfigProvider $metricConfigProvider,
        bool $enabled,
    ) {
        // Resolve the gate once (so metric config lookup happens once per request at most)
        $this->enabled = $enabled && $this->isMetricEnabled($metricConfigProvider);
    }

    /**
     * @template TReturn
     *
     * @param self::OPERATION_* $operation
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function measure(string $operation, EntityDefinition $definition, Criteria $criteria, \Closure $callback): mixed
    {
        $title = $criteria->getTitle();

        if (!$this->enabled) {
            // Metric off: keep the profiler span, skip all metric work.
            return $title === null ? $callback() : Profiler::trace($title, $callback, self::PROFILER_CATEGORY);
        }

        if ($title !== null) {
            Profiler::start($title, self::PROFILER_CATEGORY, []);
        }

        $start = hrtime(true);
        try {
            $result = $callback();
        } finally {
            $durationMs = (hrtime(true) - $start) / 1_000_000;
            if ($title !== null) {
                Profiler::stop($title);
            }
        }

        $this->meter->emit(new ConfiguredMetric(
            name: self::DURATION_METRIC,
            value: $durationMs,
            labels: [
                'entity_group' => $this->entityGroupResolver->resolve($definition->getEntityName()),
                'operation' => $operation,
                'es_aware' => $criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE) ? 'yes' : 'no',
                'backend' => $this->resolveBackend($result),
            ],
        ));

        return $result;
    }

    private function isMetricEnabled(MetricConfigProvider $metricConfigProvider): bool
    {
        try {
            return $metricConfigProvider->get(self::DURATION_METRIC)->enabled;
        } catch (TelemetryException) {
            return false;
        }
    }

    private function resolveBackend(mixed $result): string
    {
        if (($result instanceof EntitySearchResult
            || $result instanceof IdSearchResult
            || $result instanceof AggregationResultCollection)
            && $result->hasState(self::ELASTICSEARCH_RESULT_STATE)
        ) {
            return self::BACKEND_ELASTICSEARCH;
        }

        return self::BACKEND_SQL;
    }
}
