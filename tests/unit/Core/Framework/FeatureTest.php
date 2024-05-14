<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[CoversClass(Feature::class)]
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
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    protected function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testFakeFeatureFlagsAreClean(): void
    {
        $_SERVER['FEATURE_ALL'] = true;
        $_SERVER['FEATURE_NEXT_0000'] = true;
        $_ENV['FEATURE_NEXT_0000'] = true;
        $_SERVER['V6_4_5_0'] = true;

        Feature::fake([], function (): void {
            static::assertFalse(Feature::isActive('FEATURE_ALL'));
            static::assertFalse(Feature::isActive('FEATURE_NEXT_0000'));
            static::assertFalse(Feature::isActive('v6.4.5.0'));
        });

        Feature::fake([], function (): void {
            $_SERVER['FEATURE_ALL'] = true;
            Feature::registerFeature('FEATURE_ONE', [
                'name' => 'Feature 1',
                'default' => true,
                'active' => true,
                'description' => 'This is a test feature',
            ]);
            Feature::registerFeature('FEATURE_TWO', [
                'name' => 'Feature 1',
                'default' => true,
                'active' => false,
                'description' => 'This is a test feature',
            ]);

            static::assertFalse(Feature::isActive('FEATURE_TWO'));
            static::assertTrue(Feature::isActive('FEATURE_ONE'));
        });

        static::assertArrayHasKey('FEATURE_ALL', $_SERVER);
        static::assertTrue($_SERVER['FEATURE_ALL']);

        static::assertArrayHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertTrue($_SERVER['FEATURE_NEXT_0000']);

        static::assertArrayHasKey('FEATURE_NEXT_0000', $_ENV);
        static::assertTrue($_ENV['FEATURE_NEXT_0000']);

        static::assertArrayHasKey('V6_4_5_0', $_SERVER);
        static::assertTrue($_SERVER['V6_4_5_0']);
    }

    public function testNonMajorIsNotActiveIfSet(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ONE' => [
                'name' => 'Feature 1',
                'default' => true,
                'active' => false,
                'major' => false,
                'description' => 'This is a test feature',
            ],
        ]);

        $_ENV['FEATURE_ONE'] = true;

        static::assertFalse(Feature::isActive('FEATURE_ONE'));
    }

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

    #[DisabledFeatures(['v6.5.0.0'])]
    public function testTriggerDeprecationOrThrowDoesNotThrowIfUninitialized(): void
    {
        Feature::resetRegisteredFeatures();

        // no throw
        Feature::triggerDeprecationOrThrow('v6.5.0.0', 'test');

        $this->expectNotToPerformAssertions();
    }

    public function testSetActive(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ONE' => [
                'name' => 'Feature 1',
                'default' => true,
                'active' => true,
                'description' => 'This is a test feature',
            ],
        ]);

        static::assertTrue(Feature::isActive('FEATURE_ONE'));

        Feature::setActive('FEATURE_ONE', false);

        static::assertFalse(Feature::isActive('FEATURE_ONE'));

        Feature::setActive('FEATURE_ONE', true);

        static::assertTrue(Feature::isActive('FEATURE_ONE'));
    }

    public function testSetActiveOnUnregisteredFeature(): void
    {
        static::expectException(FeatureException::class);
        static::expectExceptionMessage('Feature "FEATURE_TWO" is not registered.');

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ONE' => [
                'name' => 'Feature 1',
                'default' => true,
                'active' => true,
                'description' => 'This is a test feature',
            ],
        ]);

        static::assertFalse(Feature::has('FEATURE_TWO'));
        Feature::setActive('FEATURE_TWO', false);
    }

    public function testTriggerDeprecationOrThrowThrows(): void
    {
        $this->expectException(FeatureException::class);

        Feature::triggerDeprecationOrThrow('v6.5.0.0', 'test');
    }

    #[DisabledFeatures(['v6.5.0.0'])]
    #[DataProvider('callSilentIfInactiveProvider')]
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

    #[DataProvider('deprecatedMethodMessageProvider')]
    public function testDeprecatedMethodMessage(string $expectedMessage, string $className, string $methodName): void
    {
        $message = Feature::deprecatedMethodMessage($className, $methodName, 'v6.7.0.0');
        static::assertSame($expectedMessage, $message);
    }

    public static function deprecatedMethodMessageProvider(): \Generator
    {
        yield 'message with class and method string' => [
            'Method "Shopware\Tests\Unit\Core\Framework\FeatureTest::deprecatedMethodMessageProvider()" is deprecated and will be removed in v6.7.0.0.',
            __CLASS__,
            'deprecatedMethodMessageProvider',
        ];

        yield 'message with class and method magic constant' => [
            'Method "Shopware\Tests\Unit\Core\Framework\FeatureTest::deprecatedMethodMessageProvider()" is deprecated and will be removed in v6.7.0.0.',
            __CLASS__,
            __METHOD__,
        ];
    }

    public static function callSilentIfInactiveProvider(): \Generator
    {
        yield 'Execute a callable with inactivated feature flag in silent' => [
            'v6.5.0.0', 'deprecated message', function ($deprecatedMessage, $errorMessage): void {
                static::assertNull($errorMessage);
            },
        ];

        yield 'Execute a callable with inactivated feature flag and throw a deprecated message' => [
            // `v6.4.0.0` is not registered as feature flag, therefore it will always throw the deprecation
            'v6.4.0.0', 'deprecated message', function ($deprecatedMessage, $errorMessage): void {
                static::assertFalse(strpos($deprecatedMessage, (string) $errorMessage));
            },
        ];
    }
}
