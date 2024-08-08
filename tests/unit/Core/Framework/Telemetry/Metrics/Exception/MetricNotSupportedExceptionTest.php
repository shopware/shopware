<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(MetricNotSupportedException::class)]
class MetricNotSupportedExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        $transport = $this->createMock(MetricTransportInterface::class);
        $metric = $this->createMock(MetricInterface::class);
        $exception = new MetricNotSupportedException($metric, $transport);
        static::assertSame('TELEMETRY__METRIC_NOT_SUPPORTED', $exception->getErrorCode());
    }
}
