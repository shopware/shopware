<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Attribute;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter as UpDownCounterMetric;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 */
#[Package('core')]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class UpDownCounter extends BaseMetricAttribute
{
    public function __construct(
        private string $name,
        private int|float|string $value,
        private ?string $description = null,
        private ?string $unit = null,
        private string $type = self::TYPE_VALUE,
    ) {
        parent::__construct($this->value, $this->type);
    }

    public function getMetric(object $decorated): MetricInterface
    {
        $value = $this->getValue($decorated);

        if (!\is_int($value) && !\is_float($value)) {
            throw TelemetryException::metricInvalidAttributeValue($this, $value, $this->name);
        }

        return new UpDownCounterMetric(
            $this->name,
            $value,
            $this->description,
            $this->unit,
        );
    }
}
