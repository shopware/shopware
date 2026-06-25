<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Metrics\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelPolicy;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MetricConfig::class)]
class MetricConfigTest extends TestCase
{
    public function testFromDefinition(): void
    {
        $config = MetricConfig::fromDefinition('my_metric', [
            'type' => 'counter',
            'description' => 'My metric description',
            'unit' => 'ms',
            'enabled' => true,
            'parameters' => ['foo' => 'bar'],
            'labels' => [
                'route' => [
                    'allowed_values' => ['a', 'b'],
                    'policy' => 'replace',
                ],
            ],
        ]);

        static::assertSame('my_metric', $config->name);
        static::assertSame('My metric description', $config->description);
        static::assertSame(Type::COUNTER, $config->type);
        static::assertTrue($config->enabled);
        static::assertSame(['foo' => 'bar'], $config->parameters);
        static::assertSame('ms', $config->unit);
        static::assertArrayHasKey('route', $config->labels);
        static::assertInstanceOf(LabelConfig::class, $config->labels['route']);
        static::assertSame(['a', 'b'], $config->labels['route']->allowedValues);
        static::assertSame(LabelPolicy::REPLACE, $config->labels['route']->policy);
    }
}
