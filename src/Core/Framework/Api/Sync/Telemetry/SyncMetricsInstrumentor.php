<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync\Telemetry;

use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopware\Core\Framework\Telemetry\Instrumentation\OperationResult;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Telemetry;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Framework\Api\Sync\SyncService}: derives the Sync API
 * metrics (operations per request, request duration, affected entities) from a single `sync()` call.
 * Failed requests are timed too, kept out of the healthy distribution by the `result` label.
 *
 * Merely-hot path: relies on the Meter's early-return when telemetry is disabled.
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

    /**
     * Label value for requests without an explicit indexing behavior (synchronous indexing).
     */
    private const INDEXING_BEHAVIOR_DEFAULT = 'default';

    public function __construct(
        private readonly Telemetry $telemetry,
        private readonly EntityGroupResolver $entityGroupResolver,
    ) {
    }

    /**
     * @param list<SyncOperation> $operations
     * @param \Closure(): SyncResult $callback
     */
    public function measure(array $operations, SyncBehavior $behavior, \Closure $callback): SyncResult
    {
        $this->telemetry->emit(new ConfiguredMetric(
            name: 'api.sync.operations.count',
            value: \count($operations),
        ));

        $syncResult = $this->telemetry->instrument($callback, new DurationMetric(
            name: 'api.sync.duration',
            labels: static fn (?SyncResult $result, ?\Throwable $e): array => [
                'indexing_behavior' => $behavior->getIndexingBehavior() ?? self::INDEXING_BEHAVIOR_DEFAULT,
                'result' => OperationResult::fromOutcome($e),
            ],
        ));

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
            $this->telemetry->emit(new ConfiguredMetric(
                name: 'api.sync.entities.affected',
                value: $count,
                labels: ['entity_group' => $group, 'action' => $action],
            ));
        }
    }
}
