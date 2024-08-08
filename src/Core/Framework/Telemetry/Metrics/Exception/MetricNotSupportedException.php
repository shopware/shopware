<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
class MetricNotSupportedException extends TelemetryException
{
    final public const METRIC_NOT_SUPPORTED = 'TELEMETRY__METRIC_NOT_SUPPORTED';

    public function __construct(
        public readonly MetricInterface $metric,
        public readonly MetricTransportInterface $transport,
        public string $errorCode = self::METRIC_NOT_SUPPORTED,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, $errorCode, $message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return self::METRIC_NOT_SUPPORTED;
    }
}
