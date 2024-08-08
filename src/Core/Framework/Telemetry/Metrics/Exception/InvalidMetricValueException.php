<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\MetricAttributeInterface;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
class InvalidMetricValueException extends TelemetryException
{
    final public const METRIC_INVALID_ATTRIBUTE_VALUE = 'TELEMETRY__INVALID_METRIC_VALUE';

    public function __construct(
        public readonly MetricAttributeInterface $attribute,
        public readonly mixed $value,
        public string $errorCode = self::METRIC_INVALID_ATTRIBUTE_VALUE,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, $this->errorCode, $message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return self::METRIC_INVALID_ATTRIBUTE_VALUE;
    }
}
