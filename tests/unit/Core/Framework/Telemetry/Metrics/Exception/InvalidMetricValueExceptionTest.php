<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Attribute\MetricAttributeInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\InvalidMetricValueException;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(InvalidMetricValueException::class)]
class InvalidMetricValueExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $attribute = $this->createMock(MetricAttributeInterface::class);
        $exception = new InvalidMetricValueException($attribute, null);
        static::assertSame('TELEMETRY__INVALID_METRIC_VALUE', $exception->getErrorCode());
    }
}
