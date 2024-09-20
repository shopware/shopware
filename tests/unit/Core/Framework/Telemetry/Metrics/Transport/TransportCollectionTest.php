<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(TransportCollection::class)]
class TransportCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $config = new TransportConfig(
            [MetricConfig::fromDefinition('test', ['type' => Type::GAUGE->value, 'description' => 'test', 'enabled' => true])]
        );

        $configProvider = $this->createMock(TransportConfigProvider::class);
        $configProvider->expects(static::once())
            ->method('getTransportConfig')
            ->willReturn($config);

        $transport1 = $this->createMock(MetricTransportInterface::class);
        $transport2 = $this->createMock(MetricTransportInterface::class);

        $factory1 = $this->createMock(MetricTransportFactoryInterface::class);
        $factory1->expects(static::once())
            ->method('create')
            ->with($config)
            ->willReturn($transport1);

        $factory2 = $this->createMock(MetricTransportFactoryInterface::class);
        $factory2->expects(static::once())
            ->method('create')
            ->with($config)
            ->willReturn($transport2);

        $factories = new \ArrayIterator([$factory1, $factory2]);

        $collection = TransportCollection::create($factories, $configProvider);

        $transports = iterator_to_array($collection->getIterator());
        static::assertCount(2, $transports);
        static::assertSame($transport1, $transports[0]);
        static::assertSame($transport2, $transports[1]);
    }
}
