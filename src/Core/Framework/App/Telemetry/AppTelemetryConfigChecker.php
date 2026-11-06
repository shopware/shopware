<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Evaluates an app's telemetry config against the compiled metric configuration on the install path:
 * accepted entries are persisted, the rest is reported as violations for the install-time warning log.
 *
 * @internal only for use by the app-system
 *
 * @phpstan-import-type LabelValuesEntry from AppTelemetryConfig
 *
 * @phpstan-type CheckResult array{accepted: AppTelemetryConfig, violations: list<string>}
 */
#[Package('framework')]
class AppTelemetryConfigChecker
{
    /**
     * @param array<string, array{labels?: array<string, array{allowed_values?: list<string>, add_allowed_values?: list<string>, policy?: string}>}> $definitions the `shopware.telemetry.metrics.definitions` parameter
     * @param array<string, array<string, string>> $mappings the `shopware.telemetry.metrics.mappings` parameter
     */
    public function __construct(
        private readonly array $definitions,
        private readonly array $mappings,
    ) {
    }

    /**
     * @return CheckResult
     */
    public function check(AppTelemetryConfig $config): array
    {
        $violations = [];

        $acceptedLabelValues = [];
        foreach ($config->labelValues as $entry) {
            $violation = $this->checkLabelValuesEntry($entry);
            if ($violation !== null) {
                $violations[] = $violation;

                continue;
            }

            $acceptedLabelValues[] = $entry;
        }

        $acceptedMappings = [];
        foreach ($config->mappings as $name => $entries) {
            if (!\array_key_exists($name, $this->mappings)) {
                $violations[] = \sprintf('mapping "%s" does not exist', $name);

                continue;
            }

            $accepted = [];
            foreach ($entries as $key => $value) {
                if (\array_key_exists($key, $this->mappings[$name])) {
                    $violations[] = \sprintf('mapping "%s" key "%s" is already declared and cannot be overridden', $name, $key);

                    continue;
                }

                $accepted[$key] = $value;
            }

            if ($accepted !== []) {
                $acceptedMappings[$name] = $accepted;
            }
        }

        return [
            'accepted' => new AppTelemetryConfig($acceptedLabelValues, $acceptedMappings),
            'violations' => $violations,
        ];
    }

    /**
     * @param LabelValuesEntry $entry
     */
    private function checkLabelValuesEntry(array $entry): ?string
    {
        $definition = $this->definitions[$entry['metric']] ?? null;
        if ($definition === null) {
            return \sprintf('metric "%s" does not exist', $entry['metric']);
        }

        $label = $definition['labels'][$entry['label']] ?? null;
        if ($label === null) {
            return \sprintf('metric "%s" has no label "%s"', $entry['metric'], $entry['label']);
        }

        if (($label['allowed_values'] ?? []) !== []) {
            return \sprintf('metric "%s" label "%s" is locked by `allowed_values`; app values are ignored', $entry['metric'], $entry['label']);
        }

        if (($label['add_allowed_values'] ?? []) === []) {
            return \sprintf('metric "%s" label "%s" does not take value contributions (open label)', $entry['metric'], $entry['label']);
        }

        return null;
    }
}
