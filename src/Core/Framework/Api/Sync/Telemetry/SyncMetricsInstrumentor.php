<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync\Telemetry;

use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Framework\Api\Sync\SyncService}: derives the Sync API
 * metrics (operations per request, request duration, affected entities) from a single `sync()` call.
 *
 * Times manually on rather than via `Telemetry::instrument()`, as `result` label that is only known once
 * `sync()` returns or throws. Failed requests are timed separately to keep distributions separate.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class SyncMetricsInstrumentor
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    private const RESULT_SUCCESS = 'success';
    private const RESULT_FAILED = 'failed';

    /**
     * Label value for requests without an explicit indexing behavior (synchronous indexing).
     */
    private const INDEXING_BEHAVIOR_DEFAULT = 'default';

    public function __construct(
        private readonly Meter $meter,
        private readonly EntityGroupResolver $entityGroupResolver,
    ) {
    }

    /**
     * @param list<SyncOperation> $operations
     * @param \Closure(): SyncResult $callback
     */
    public function measure(array $operations, SyncBehavior $behavior, \Closure $callback): SyncResult
    {
        $this->meter->emit(new ConfiguredMetric(
            name: 'api.sync.operations.count',
            value: \count($operations),
        ));

        $result = self::RESULT_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            $syncResult = $callback();
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'api.sync.duration',
                value: $timer->getElapsedMs(),
                labels: [
                    'indexing_behavior' => $behavior->getIndexingBehavior() ?? self::INDEXING_BEHAVIOR_DEFAULT,
                    'result' => $result,
                ],
            ));
        }

        $this->emitAffectedEntities($syncResult->getData(), self::ACTION_UPSERT);
        $this->emitAffectedEntities($syncResult->getDeleted(), self::ACTION_DELETE);

        return $syncResult;
    }

    /**
     * One emit per (entity_group, action) pair: per-entity counts are pre-aggregated into the bounded
     * group set, so a request touching many entities of one group produces a single emit.
     *
     * @param array<string, array<int, mixed>> $primaryKeysByEntity
     */
    private function emitAffectedEntities(array $primaryKeysByEntity, string $action): void
    {
        $countByGroup = [];
        foreach ($primaryKeysByEntity as $entityName => $primaryKeys) {
            $group = $this->entityGroupResolver->resolve((string) $entityName);
            $countByGroup[$group] = ($countByGroup[$group] ?? 0) + \count($primaryKeys);
        }

        foreach ($countByGroup as $group => $count) {
            $this->meter->emit(new ConfiguredMetric(
                name: 'api.sync.entities.affected',
                value: $count,
                labels: ['entity_group' => $group, 'action' => $action],
            ));
        }
    }
}
