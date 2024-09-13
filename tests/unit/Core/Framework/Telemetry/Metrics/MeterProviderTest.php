<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[CoversClass(MeterProvider::class)]
class MeterProviderTest extends TestCase
{
    private MockObject&ContainerInterface $container;

    private Meter $meter;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->meter = $this->createMock(Meter::class);
    }

    public function testBindMeter(): void
    {
        $this->container->expects(static::once())
            ->method('has')
            ->with(Meter::class)
            ->willReturn(true);

        $this->container->expects(static::once())
            ->method('get')
            ->with(Meter::class)
            ->willReturn($this->meter);

        MeterProvider::bindMeter($this->container);

        static::assertSame($this->meter, MeterProvider::meter());
    }

    public function testBindMeterWhenMeterNotAvailable(): void
    {
        $this->container->expects(static::once())
            ->method('has')
            ->with(Meter::class)
            ->willReturn(false);

        MeterProvider::bindMeter($this->container);

        static::assertNull(MeterProvider::meter());
    }
}
