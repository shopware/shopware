<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Owns the `dal.write.duration` metric for {@see \Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter}
 * write operations. The timed code covers payload normalization, command extraction and query
 * execution; the `EntityWrittenContainerEvent` dispatching (indexing) stays outside.
 *
 * `EntityWriter::sync()` is intentionally not instrumented: it spans multiple entity definitions per call
 * (no meaningful `entity_group`) and is covered end-to-end by `api.sync.duration`.
 *
 * Times manually on `Meter`: the `result` label is only known once the write returns or throws. Failed
 * writes (e.g. constraint violations) are timed too, kept out of the healthy distribution by the label.
 *
 * Merely-hot path: relies on `Meter::emit`'s early-return when telemetry is disabled, no compiler-pass
 * gating (writes are far rarer than the gated read path).
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class DalWriteInstrumentor
{
    /**
     * Covers insert, update and upsert (same code shape); a finer split would only produce label values
     * that dashboards aggregate away. Deletes keep their own value for their distinct cascade profile.
     */
    public const OPERATION_WRITE = 'write';
    public const OPERATION_DELETE = 'delete';

    private const RESULT_SUCCESS = 'success';
    private const RESULT_FAILED = 'failed';

    private const DURATION_METRIC = 'dal.write.duration';

    public function __construct(
        private readonly Meter $meter,
        private readonly EntityGroupResolver $entityGroupResolver,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param self::OPERATION_* $operation
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function measure(string $operation, EntityDefinition $definition, \Closure $callback): mixed
    {
        $result = self::RESULT_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            return $callback();
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: self::DURATION_METRIC,
                value: $timer->getElapsedMs(),
                labels: [
                    'entity_group' => $this->entityGroupResolver->resolve($definition->getEntityName()),
                    'operation' => $operation,
                    'result' => $result,
                ],
            ));
        }
    }
}
