<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TelemetryException::class)]
class TelemetryExceptionTest extends TestCase
{
    public function testUnknownMetricLabel(): void
    {
        $exception = TelemetryException::unknownMetricLabel('http.requests', 'invalid_label');

        static::assertSame('TELEMETRY__UNKNOWN_METRIC_LABEL', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Unknown label "invalid_label" for metric "http.requests". The label must be declared in the metric definition.', $exception->getMessage());
    }

    public function testInstrumentWithoutMetricOrSpan(): void
    {
        $exception = TelemetryException::instrumentWithoutMetricOrSpan();

        static::assertSame('TELEMETRY__INSTRUMENT_WITHOUT_METRIC_OR_SPAN', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Telemetry::instrument() was called without a DurationMetric or a Span. Provide at least one — otherwise call the callback directly.', $exception->getMessage());
    }
}
