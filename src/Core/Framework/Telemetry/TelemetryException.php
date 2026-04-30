<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class TelemetryException extends HttpException
{
    public const UNKNOWN_LABEL_NAME = 'TELEMETRY__UNKNOWN_METRIC_LABEL';

    public static function metricNotSupported(
        Metric $metric,
        MetricTransportInterface $transport
    ): MetricNotSupportedException {
        return new MetricNotSupportedException(
            metric: $metric,
            transport: $transport,
            message: \sprintf('Metric %s, not supported by transport %s', $metric::class, $transport::class),
        );
    }

    /**
     * @internal
     */
    public static function metricMissingConfiguration(string $metric): MissingMetricConfigurationException
    {
        return new MissingMetricConfigurationException(
            metric: $metric,
            message: \sprintf('Missing configuration for metric %s', $metric),
        );
    }

    /**
     * @internal
     */
    public static function unknownMetricLabel(string $metricName, string $labelName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_LABEL_NAME,
            \sprintf('Unknown label "%s" for metric "%s". The label must be declared in the metric definition.', $labelName, $metricName),
        );
    }
}
