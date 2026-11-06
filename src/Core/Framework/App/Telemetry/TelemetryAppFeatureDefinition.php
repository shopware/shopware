<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Maps `Resources/config/telemetry.xml` to one `app_feature` row of type `telemetry` per app.
 *
 * Validation on this path is lenient: a key for an unknown mapping, a bare `*`, or a key already declared
 * in config never fails the install — it is skipped and logged once as a warning, and only the accepted
 * keys are persisted. A file that does not parse or violates the schema fails the install, like the other
 * app configuration files.
 *
 * @internal only for use by the app-system
 *
 * @extends AppFeatureDefinition<AppTelemetryConfig>
 */
#[Package('framework')]
final class TelemetryAppFeatureDefinition extends AppFeatureDefinition
{
    final public const TYPE = 'telemetry';

    /**
     * @param array<string, array<string, string>> $mappings the `shopware.telemetry.metrics.mappings` parameter
     */
    public function __construct(
        private readonly array $mappings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return AppTelemetryConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        if (!$appFilesystem->hasFile('Resources', 'config', 'telemetry.xml')) {
            return [];
        }

        $config = AppTelemetryConfig::createFromXmlFile($appFilesystem->path('Resources', 'config', 'telemetry.xml'));

        $accepted = [];
        $violations = [];
        foreach ($config->mappings as $name => $keys) {
            if (!\array_key_exists($name, $this->mappings)) {
                $violations[] = \sprintf('mapping "%s" does not exist', $name);

                continue;
            }

            foreach ($keys as $key) {
                if ($key === '*') {
                    $violations[] = \sprintf('mapping "%s": a bare "*" would claim the whole keyspace', $name);

                    continue;
                }

                if (\array_key_exists($key, $this->mappings[$name])) {
                    $violations[] = \sprintf('mapping "%s" key "%s" is declared in config and cannot be claimed', $name, $key);

                    continue;
                }

                $accepted[$name][] = $key;
            }
        }

        if ($violations !== []) {
            $this->logger->warning(\sprintf(
                "App \"%s\": telemetry.xml entries were skipped:\n- %s",
                $manifest->getMetadata()->getName(),
                implode("\n- ", $violations),
            ));
        }

        return $accepted === [] ? [] : [new AppTelemetryConfig($accepted)];
    }

    /**
     * @param AppTelemetryConfig $declared
     *
     * @return array{mappings: array<string, list<string>>}
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        // the app's declaration always wins; there is no shop-side state to preserve
        return $declared->toArray();
    }

    /**
     * @param array{mappings?: array<string, list<string>>} $payload
     */
    public function fromPayload(array $payload): AppTelemetryConfig
    {
        return AppTelemetryConfig::fromArray($payload);
    }
}
