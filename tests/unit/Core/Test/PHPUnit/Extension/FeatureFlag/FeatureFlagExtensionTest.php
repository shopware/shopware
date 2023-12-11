<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\FeatureFlag;

use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[CoversClass(FeatureFlagExtension::class)]
class FeatureFlagExtensionTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private static array $serverVars = [];

    #[BeforeClass]
    public static function registerTestFeature(): void
    {
        self::$serverVars = $_SERVER;

        Feature::registerFeature('foobar');
        $_SERVER['FOOBAR'] = true;

        static::assertTrue(Feature::isActive('foobar'));

        // FEATURE_NEXT_12345 is a test feature flag set from the test shopware.yaml
        // The extension should convert it to a server var and set it to true
        static::assertArrayNotHasKey('FEATURE_NEXT_12345', $_SERVER);
        static::assertFalse(Feature::isActive('FEATURE_NEXT_12345'));
    }

    #[AfterClass]
    public static function restoreServerVars(): void
    {
        $_SERVER = self::$serverVars;
    }

    public function testFeatureIsSet(): void
    {
        static::assertTrue(Feature::isActive('foobar'));

        static::assertTrue($_SERVER['FEATURE_NEXT_12345']);
        static::assertTrue(Feature::isActive('FEATURE_NEXT_12345'));
    }

    #[DisabledFeatures(['foobar'])]
    public function testFeatureIsDisabled(): void
    {
        static::assertFalse(Feature::isActive('foobar'));
    }
}
