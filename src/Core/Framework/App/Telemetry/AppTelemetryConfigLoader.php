<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Loads the persisted telemetry claims of all active apps (their `telemetry` app-feature rows) into one
 * table per mapping, `key → label value`, once per process. The label value is derived from the app's
 * technical name in snake case (`SwagBlog` → `swag_blog`); a key claimed by more than one app keeps the
 * first app by name, logged at debug level.
 *
 * There is no per-request reset: one storage read per request or message is not a cost telemetry should
 * add, so long-running processes (workers) pick up changed claims on restart.
 *
 * The read is lazy and fail-soft: metrics are emitted during error handling and early boot, so on any
 * storage failure the loader logs a warning once and the consumers keep the compiled config for the rest
 * of the process — telemetry must never add a failure mode to the paths it observes. The storage is
 * resolved through a service closure on first use: the DAL instrumentation reaches this loader from every
 * entity repository, while the storage's feature definitions depend on entity repositories themselves, so
 * an eager dependency would form a cycle.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppTelemetryConfigLoader
{
    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $mappings = null;

    /**
     * @param \Closure(): AppFeatureStorage $appFeatureStorageLocator
     */
    public function __construct(
        private readonly \Closure $appFeatureStorageLocator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, array<string, string>> mapping name → (claimed key → app-derived label value)
     */
    public function load(): array
    {
        return $this->mappings ??= $this->doLoad();
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function doLoad(): array
    {
        try {
            $features = ($this->appFeatureStorageLocator)()->forActiveApps(AppTelemetryConfig::class);
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf(
                'App telemetry claims could not be loaded and are ignored for this process: %s',
                $e->getMessage(),
            ));

            return [];
        }

        $converter = new CamelCaseToSnakeCaseNameConverter();
        $mappings = [];
        foreach ($features as $feature) {
            $value = $converter->normalize($feature->appName);

            foreach ($feature->config->mappings as $name => $keys) {
                foreach ($keys as $key) {
                    if (isset($mappings[$name][$key])) {
                        $this->logger->debug(\sprintf(
                            'Telemetry mapping "%s" key "%s" is claimed by more than one app; keeping "%s".',
                            $name,
                            $key,
                            $mappings[$name][$key],
                        ));

                        continue;
                    }

                    $mappings[$name][$key] = $value;
                }
            }
        }

        return $mappings;
    }
}
