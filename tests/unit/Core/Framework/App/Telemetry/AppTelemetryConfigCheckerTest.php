<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigChecker;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppTelemetryConfigChecker::class)]
class AppTelemetryConfigCheckerTest extends TestCase
{
    private const DEFINITIONS = [
        'order.placed.count' => [
            'labels' => [
                'payment_method' => ['add_allowed_values' => ['core_method', 'other']],
                'result' => ['allowed_values' => ['success', 'failed']],
                'sales_channel_type' => ['policy' => 'open'],
            ],
        ],
    ];

    private const MAPPINGS = [
        'entity_group' => ['product' => 'product'],
        'sales_channel_type' => [],
    ];

    public function testAcceptsValidEntries(): void
    {
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'order.placed.count', 'label' => 'payment_method', 'values' => ['swag_method']]],
            mappings: ['entity_group' => ['swag_blog_*' => 'blog']],
        );

        ['accepted' => $accepted, 'violations' => $violations] = $this->checker()->check($config);

        static::assertSame([], $violations);
        static::assertSame($config->labelValues, $accepted->labelValues);
        static::assertSame($config->mappings, $accepted->mappings);
    }

    public function testRejectsUnknownMetric(): void
    {
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'nope.count', 'label' => 'payment_method', 'values' => ['v']]],
        );

        ['accepted' => $accepted, 'violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['metric "nope.count" does not exist'], $violations);
        static::assertTrue($accepted->isEmpty());
    }

    public function testRejectsUnknownLabel(): void
    {
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'order.placed.count', 'label' => 'nope', 'values' => ['v']]],
        );

        ['violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['metric "order.placed.count" has no label "nope"'], $violations);
    }

    public function testRejectsLockedLabel(): void
    {
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'order.placed.count', 'label' => 'result', 'values' => ['v']]],
        );

        ['violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['metric "order.placed.count" label "result" is locked by `allowed_values`; app values are ignored'], $violations);
    }

    public function testRejectsValuesForOpenLabel(): void
    {
        $config = new AppTelemetryConfig(
            labelValues: [['metric' => 'order.placed.count', 'label' => 'sales_channel_type', 'values' => ['v']]],
        );

        ['violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['metric "order.placed.count" label "sales_channel_type" does not take value contributions (open label)'], $violations);
    }

    public function testRejectsUnknownMappingName(): void
    {
        $config = new AppTelemetryConfig(mappings: ['nope' => ['a' => 'b']]);

        ['accepted' => $accepted, 'violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['mapping "nope" does not exist'], $violations);
        static::assertTrue($accepted->isEmpty());
    }

    public function testRejectsCollidingMappingKeyButKeepsTheRest(): void
    {
        $config = new AppTelemetryConfig(mappings: ['entity_group' => [
            'product' => 'mine',
            'swag_blog_*' => 'blog',
        ]]);

        ['accepted' => $accepted, 'violations' => $violations] = $this->checker()->check($config);

        static::assertSame(['mapping "entity_group" key "product" is already declared and cannot be overridden'], $violations);
        static::assertSame(['entity_group' => ['swag_blog_*' => 'blog']], $accepted->mappings);
    }

    private function checker(): AppTelemetryConfigChecker
    {
        return new AppTelemetryConfigChecker(self::DEFINITIONS, self::MAPPINGS);
    }
}
