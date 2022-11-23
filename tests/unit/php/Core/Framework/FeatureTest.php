<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @coversDefaultClass \Shopware\Core\Framework\Feature
 */
class FeatureTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $serverVarsBackup;

    /**
     * @var array<mixed>
     */
    private array $envVarsBackup;

    /**
     * @var array<mixed>
     */
    private array $featureConfigBackup;

    public function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
    }

    public function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    /**
     * @covers ::fake
     */
    public function testFakeFeatureFlagsAreClean(): void
    {
        $_SERVER['FEATURE_ALL'] = true;
        $_SERVER['FEATURE_NEXT_0000'] = true;
        $_ENV['FEATURE_NEXT_0000'] = true;
        $_SERVER['V6_4_5_0'] = true;
        $_SERVER['PERFORMANCE_TWEAKS'] = true;

        Feature::fake([], function (): void {
            static::assertFalse(Feature::isActive('FEATURE_ALL'));
            static::assertFalse(Feature::isActive('FEATURE_NEXT_0000'));
            static::assertFalse(Feature::isActive('v6.4.5.0'));
            static::assertFalse(Feature::isActive('PERFORMANCE_TWEAKS'));
        });

        static::assertArrayHasKey('FEATURE_ALL', $_SERVER);
        static::assertTrue($_SERVER['FEATURE_ALL']);

        static::assertArrayHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertTrue($_SERVER['FEATURE_NEXT_0000']);

        static::assertArrayHasKey('FEATURE_NEXT_0000', $_ENV);
        static::assertTrue($_ENV['FEATURE_NEXT_0000']);

        static::assertArrayHasKey('V6_4_5_0', $_SERVER);
        static::assertTrue($_SERVER['V6_4_5_0']);

        static::assertArrayHasKey('PERFORMANCE_TWEAKS', $_SERVER);
        static::assertTrue($_SERVER['PERFORMANCE_TWEAKS']);
    }

    /**
     * @covers ::fake
     */
    public function testFakeRestoresFeatureConfigAndEnv(): void
    {
        $beforeFeatureFlagConfig = Feature::getRegisteredFeatures();
        $beforeServerEnv = $_SERVER;

        Feature::fake([], function (): void {
            $_SERVER = ['asdf' => 'foo'];
            Feature::resetRegisteredFeatures();
            Feature::registerFeature('foobar');
        });

        static::assertSame($beforeFeatureFlagConfig, Feature::getRegisteredFeatures());
        static::assertSame($beforeServerEnv, $_SERVER);
    }

    /**
     * @covers ::fake
     */
    public function testFakeSetsFeatures(): void
    {
        static::assertArrayNotHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertArrayNotHasKey('V6_4_5_0', $_SERVER);

        Feature::fake(['FEATURE_NEXT_0000', 'v6.4.5.0'], function (): void {
            static::assertArrayHasKey('FEATURE_NEXT_0000', $_SERVER);
            static::assertTrue($_SERVER['FEATURE_NEXT_0000']);
            static::assertTrue(Feature::isActive('FEATURE_NEXT_0000'));

            static::assertArrayHasKey('V6_4_5_0', $_SERVER);
            static::assertTrue($_SERVER['V6_4_5_0']);
            static::assertTrue(Feature::isActive('v6.4.5.0'));
        });

        static::assertArrayNotHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertArrayNotHasKey('v6.4.5.0', $_SERVER);
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     *
     * @covers ::triggerDeprecationOrThrow
     */
    public function testTriggerDeprecationOrThrowDoesNotThrowIfUninitialized(): void
    {
        Feature::resetRegisteredFeatures();

        // no throw
        Feature::triggerDeprecationOrThrow('v6.5.0.0', 'test');

        // make phpunit happy
        static::assertTrue(true);
    }

    /**
     * @covers \Shopware\Core\Framework\Feature
     */
    public function testTriggerDeprecationOrThrowThrows(): void
    {
        static::expectException(\RuntimeException::class);

        Feature::triggerDeprecationOrThrow('v6.5.0.0', 'test');
    }

    public function callSilentIfInactiveProvider(): \Generator
    {
        yield 'Execute a callable with inactivated feature flag in silent' => [
            'v6.5.0.0', 'deprecated message', function ($deprecatedMessage, $errorMessage): void {
                static::assertNull($errorMessage);
            },
        ];

        yield 'Execute a callable with inactivated feature flag and throw a deprecated message' => [
            // `v6.4.0.0` is not registered as feature flag, therefore it will always throw the deprecation
            'v6.4.0.0', 'deprecated message', function ($deprecatedMessage, $errorMessage): void {
                static::assertTrue(strpos($deprecatedMessage, $errorMessage) !== -1);
            },
        ];
    }

    /**
     * @covers \Shopware\Core\Framework\Feature
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     * @dataProvider callSilentIfInactiveProvider
     */
    public function testCallSilentIfInactiveProvider(string $majorVersion, string $deprecatedMessage, \Closure $assertion): void
    {
        $errorMessage = null;
        set_error_handler(static function (int $errno, string $error) use (&$errorMessage): bool {
            $errorMessage = $error;

            return true;
        });

        Feature::callSilentIfInactive('v6.5.0.0', static function () use ($deprecatedMessage, $majorVersion): void {
            Feature::triggerDeprecationOrThrow($majorVersion, $deprecatedMessage);
        });
        $assertion($deprecatedMessage, $errorMessage);

        restore_error_handler();
    }
}
