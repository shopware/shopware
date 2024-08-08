<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\MetricAttributeInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('core')]
abstract class TelemetryException extends HttpException
{
    public static function metricNotSupported(
        MetricInterface $metric,
        MetricTransportInterface $transport
    ): MetricNotSupportedException {
        return new MetricNotSupportedException(
            metric: $metric,
            transport: $transport,
            message: \sprintf('Metric %s, not supported by transport %s', $metric::class, $transport::class),
        );
    }

    public static function metricInvalidAttributeValue(
        MetricAttributeInterface $attribute,
        mixed $value,
        string $metricName,
    ): InvalidMetricValueException {
        return new InvalidMetricValueException(
            attribute: $attribute,
            value: $value,
            message: \sprintf('Invalid value type %s retrieved from the attribute %s for the metric %s', \gettype($value), $attribute::class, $metricName),
        );
    }
}
