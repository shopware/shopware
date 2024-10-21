<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class MissingMetricConfigurationException extends TelemetryException
{
    final public const METRIC_MISSING_CONFIGURATION = 'TELEMETRY__MISSING_METRIC_CONFIGURATION';

    public function __construct(
        public readonly string $metric,
        public string $errorCode = self::METRIC_MISSING_CONFIGURATION,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, $this->errorCode, $message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return self::METRIC_MISSING_CONFIGURATION;
    }
}
