<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 *
 * Validates and processes metric labels according to the metric definition:
 * - Unknown label names: throw in dev/test, log error in prod
 * - Unknown label values: apply type-aware policy (replace/discard/open)
 */
#[Package('framework')]
class MetricLabelProcessor
{
    private const ADDITIVE_TYPES = [Type::COUNTER, Type::HISTOGRAM, Type::UPDOWN_COUNTER];

    public function __construct(
        private readonly string $replacementValue,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
    }

    /**
     * @param array<non-empty-string, string|bool|float|int> $labels
     *
     * @return array<non-empty-string, string|bool|float|int>|null processed labels, or null if a discard policy triggered
     */
    public function process(MetricConfig $metricConfig, array $labels): ?array
    {
        $configuredLabels = $metricConfig->labels;
        $processed = [];

        foreach ($labels as $name => $value) {
            if (!isset($configuredLabels[$name])) {
                $exception = TelemetryException::unknownMetricLabel($metricConfig->name, $name);

                if ($this->isDevOrTest()) {
                    throw $exception;
                }

                $this->logger->error($exception->getMessage(), ['exception' => $exception]);

                continue;
            }

            $labelConfig = $configuredLabels[$name];
            $policy = $labelConfig->policy ?? $this->defaultPolicyForType($metricConfig->type);

            if ($policy === LabelPolicy::OPEN) {
                $processed[$name] = $value;

                continue;
            }

            $allowedValues = $labelConfig->allowedValues ?? [];

            if (\in_array($value, $allowedValues, true)) {
                $processed[$name] = $value;

                continue;
            }

            if ($policy === LabelPolicy::DISCARD) {
                return null;
            }

            // Surface potential typos collapsing into the `other` bucket alongside truly uncategorized values)
            if ($this->isDevOrTest()) {
                $this->logger->notice(\sprintf(
                    'Metric "%s" label "%s" value %s is not in the allowed list and was replaced with "%s".',
                    $metricConfig->name,
                    $name,
                    var_export($value, true),
                    $this->replacementValue,
                ));
            }

            $processed[$name] = $this->replacementValue;
        }

        return $processed;
    }

    private function defaultPolicyForType(Type $metricType): LabelPolicy
    {
        return \in_array($metricType, self::ADDITIVE_TYPES, true) ? LabelPolicy::REPLACE : LabelPolicy::DISCARD;
    }

    private function isDevOrTest(): bool
    {
        return $this->environment === 'dev' || $this->environment === 'test';
    }
}
