<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\ElapsedTimer;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * Emits `number_range.allocation.duration` around {@see AbstractIncrementStorage::reserve()} - the
 * row-level-locked hot path on the MySQL backend. Rising p95/p99 with `storage=mysql` under load
 * signals `number_range_state` lock contention, the leading indicator for switching to the Redis storage.
 *
 * Decorates the configured storage so one instrumentation point covers the MySQL, Redis and any custom backend.
 * `preview`/`list`/`set`/`increaseToAtLeast` are administrative paths and pass through unmeasured.
 *
 * The `storage` label is instance-constant (`shopware.number_range.increment_storage`), resolved once via DI.
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
class IncrementStorageMetricsDecorator extends AbstractIncrementStorage
{
    private const RESULT_SUCCESS = 'success';
    private const RESULT_FAILED = 'failed';

    public function __construct(
        private readonly AbstractIncrementStorage $decorated,
        private readonly Meter $meter,
        private readonly NumberRangeTypeResolver $typeResolver,
        private readonly string $storage,
    ) {
    }

    public function reserve(array $config): int
    {
        $result = self::RESULT_SUCCESS;
        $timer = ElapsedTimer::start();

        try {
            return $this->decorated->reserve($config);
        } catch (\Throwable $e) {
            $result = self::RESULT_FAILED;

            throw $e;
        } finally {
            $this->meter->emit(new ConfiguredMetric(
                name: 'number_range.allocation.duration',
                value: $timer->getElapsedMs(),
                labels: [
                    'number_range_type' => $this->typeResolver->resolve($config['technical_name'] ?? null),
                    'storage' => $this->storage,
                    'result' => $result,
                ],
            ));
        }
    }

    public function preview(array $config): int
    {
        return $this->decorated->preview($config);
    }

    public function list(): array
    {
        return $this->decorated->list();
    }

    public function set(string $configurationId, int $value): void
    {
        $this->decorated->set($configurationId, $value);
    }

    public function increaseToAtLeast(string $configurationId, int $value): void
    {
        $this->decorated->increaseToAtLeast($configurationId, $value);
    }

    public function getDecorated(): AbstractIncrementStorage
    {
        return $this->decorated;
    }
}
