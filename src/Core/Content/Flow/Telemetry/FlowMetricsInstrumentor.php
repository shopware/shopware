<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Telemetry;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopware\Core\Framework\Telemetry\Instrumentation\OperationResult;
use Shopware\Core\Framework\Telemetry\Telemetry;

/**
 * Telemetry collaborator for {@see \Shopware\Core\Content\Flow\Dispatching\FlowExecutor}: emits
 * `flow.execution.duration` once per executed flow (a single inbound event fans out across all matched flows,
 * each timed separately). The histogram also carries its own occurrence count - can be used to calculate
 * throughput/error rate (use result label).
 *
 * This is the flow engine's end-to-end wall time (orchestration plus the in-process actions). Heavy actions
 * that offload to queue, like mail and app webhooks - are timed by the message queue metrics.
 *
 * Merely-hot path: relies on the Meter's early-return when telemetry is disabled, no compiler-pass gating.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('after-sales')]
class FlowMetricsInstrumentor
{
    public function __construct(
        private readonly Telemetry $telemetry,
        private readonly TriggerGroupResolver $triggerGroupResolver,
    ) {
    }

    /**
     * @param \Closure(): void $callback
     */
    public function measureExecution(StorableFlow $event, \Closure $callback): void
    {
        $this->telemetry->instrument($callback, new DurationMetric(
            name: 'flow.execution.duration',
            labels: fn (mixed $result, ?\Throwable $e): array => [
                'trigger_group' => $this->triggerGroupResolver->resolve($event->getName()),
                'result' => OperationResult::fromOutcome($e),
            ],
        ));
    }
}
