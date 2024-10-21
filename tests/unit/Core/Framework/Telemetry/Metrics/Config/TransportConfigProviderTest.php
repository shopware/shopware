<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(TransportConfigProvider::class)]
class TransportConfigProviderTest extends TestCase
{
    public function testGetTransportConfig(): void
    {
        $definitions = [
            'metric1' => [
                'type' => Type::COUNTER->value,
                'description' => 'Test metric 1',
                'unit' => 'unit1',
                'parameters' => ['param1' => 'value1'],
                'enabled' => true,
            ],
            'metric2' => [
                'type' => Type::GAUGE->value,
                'description' => 'Test metric 2',
                'enabled' => false,
            ],
        ];

        $configs = [];
        foreach ($definitions as $name => $definition) {
            $configs[] = MetricConfig::fromDefinition($name, $definition);
        }

        $metricConfigProvider = $this->createMock(MetricConfigProvider::class);

        $metricConfigProvider->expects(static::once())
            ->method('all')
            ->willReturn($configs);

        $provider = new TransportConfigProvider($metricConfigProvider);
        $config = $provider->getTransportConfig();
        static::assertCount(2, $config->metricsConfig);

        $metric1 = $config->metricsConfig[0];
        static::assertInstanceOf(MetricConfig::class, $metric1);
        static::assertSame('metric1', $metric1->name);
        static::assertSame('Test metric 1', $metric1->description);
        static::assertSame(Type::COUNTER, $metric1->type);
        static::assertSame('unit1', $metric1->unit);
        static::assertSame(['param1' => 'value1'], $metric1->parameters);
        static::assertTrue($metric1->enabled);

        $metric2 = $config->metricsConfig[1];
        static::assertInstanceOf(MetricConfig::class, $metric2);
        static::assertSame('metric2', $metric2->name);
        static::assertSame('Test metric 2', $metric2->description);
        static::assertSame(Type::GAUGE, $metric2->type);
        static::assertNull($metric2->unit);
        static::assertSame([], $metric2->parameters);
        static::assertFalse($metric2->enabled);
    }
}
