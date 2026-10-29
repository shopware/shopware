<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * Loads the persisted telemetry contributions of all active apps (their `telemetry` app-feature rows),
 * merged across apps, once per process. There is no per-request reset: one storage read per request or
 * message is not a cost telemetry should add, so long-running processes (workers) pick up changed app
 * contributions on restart.
 *
 * The read is lazy and fail-soft: metrics are emitted during error handling and early boot, so on any
 * storage failure the loader logs a warning once and the consumers fall back to the compiled config for
 * the rest of the process — telemetry must never add a failure mode to the paths it observes. The storage
 * is resolved through a service closure on first use: the DAL instrumentation reaches this loader from
 * every entity repository, while the storage's feature definitions depend on entity repositories
 * themselves, so an eager dependency would form a cycle.
 *
 * Entries that no longer match the compiled config after a platform upgrade are dropped by the consumers
 * (see the detectability notes in the ADR); cross-app mapping-key collisions are dropped here, first app
 * wins.
 *
 * @internal only for use by the app-system
 *
 * @phpstan-type MergedAppTelemetryConfig array{labelValues: array<string, array<string, list<string>>>, mappings: array<string, array<string, string>>}
 */
#[Package('framework')]
class AppTelemetryConfigLoader
{
    /**
     * @var MergedAppTelemetryConfig|null
     */
    private ?array $merged = null;

    /**
     * @param \Closure(): AppFeatureStorage $appFeatureStorageLocator
     */
    public function __construct(
        private readonly \Closure $appFeatureStorageLocator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return MergedAppTelemetryConfig
     */
    public function load(): array
    {
        return $this->merged ??= $this->doLoad();
    }

    /**
     * @return MergedAppTelemetryConfig
     */
    private function doLoad(): array
    {
        $merged = ['labelValues' => [], 'mappings' => []];

        try {
            $features = ($this->appFeatureStorageLocator)()->forActiveApps(AppTelemetryConfig::class);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf(
                'App telemetry contributions could not be loaded and are ignored for this process: %s',
                $e->getMessage(),
            ));

            return $merged;
        }

        foreach ($features as $feature) {
            $config = $feature->config;

            foreach ($config->labelValues as $entry) {
                $existing = $merged['labelValues'][$entry['metric']][$entry['label']] ?? [];
                $merged['labelValues'][$entry['metric']][$entry['label']] = array_values(array_unique(array_merge($existing, $entry['values'])));
            }

            foreach ($config->mappings as $name => $entries) {
                foreach ($entries as $key => $value) {
                    if (isset($merged['mappings'][$name][$key])) {
                        $this->logger->debug(\sprintf(
                            'Telemetry mapping "%s" key "%s" is contributed by more than one app; keeping the first value.',
                            $name,
                            $key,
                        ));

                        continue;
                    }

                    $merged['mappings'][$name][$key] = $value;
                }
            }
        }

        return $merged;
    }
}
