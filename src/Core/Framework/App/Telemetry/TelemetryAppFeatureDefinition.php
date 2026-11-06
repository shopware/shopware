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
 * Validation on this path is lenient: an unknown metric/label/mapping, a locked label, or a colliding
 * mapping key never fails the install — the entry is skipped and logged once as a warning, and only the
 * accepted entries are persisted. A file that does not parse or violates the schema fails the install,
 * like the other app configuration files.
 *
 * @internal only for use by the app-system
 *
 * @extends AppFeatureDefinition<AppTelemetryConfig>
 */
#[Package('framework')]
final class TelemetryAppFeatureDefinition extends AppFeatureDefinition
{
    final public const TYPE = 'telemetry';

    public function __construct(
        private readonly AppTelemetryConfigChecker $checker,
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

        ['accepted' => $accepted, 'violations' => $violations] = $this->checker->check($config);

        if ($violations !== []) {
            $this->logger->warning(\sprintf(
                "App \"%s\": telemetry.xml entries were skipped:\n- %s",
                $manifest->getMetadata()->getName(),
                implode("\n- ", $violations),
            ));
        }

        return $accepted->isEmpty() ? [] : [$accepted];
    }

    /**
     * @param AppTelemetryConfig $declared
     *
     * @return array{labelValues: list<array{metric: string, label: string, values: list<string>}>, mappings: array<string, array<string, string>>}
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        // the app's declaration always wins; there is no shop-side state to preserve
        return $declared->toArray();
    }

    /**
     * @param array{labelValues?: list<array{metric: string, label: string, values: list<string>}>, mappings?: array<string, array<string, string>>} $payload
     */
    public function fromPayload(array $payload): AppTelemetryConfig
    {
        return AppTelemetryConfig::fromArray($payload);
    }
}
