<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\ActiveFeatures;
use Shopware\Core\Test\FeatureFlagExtension;

/**
 * @internal
 *
 * @covers \Shopware\Core\Test\FeatureFlagExtension
 */
class FeatureFlagExtensionTest extends TestCase
{
    private array $serverVarsBackup;

    private array $envVarsBackup;

    private array $featureConfigBackup;

    private FeatureFlagExtension $extension;

    public function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
        $this->extension = new FeatureFlagExtension('Shopware\\Tests\\Unit\\', true);
    }

    public function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testFeatureFlagsAreClean(): void
    {
        $_SERVER['FEATURE_ALL'] = true;
        $_SERVER['FEATURE_NEXT_0000'] = true;
        $_ENV['FEATURE_NEXT_0000'] = true;
        $_SERVER['V6_4_5_0'] = true;
        $_SERVER['PERFORMANCE_TWEAKS'] = true;

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertFalse(Feature::isActive('FEATURE_ALL'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_0000'));
        static::assertFalse(Feature::isActive('v6.5.0.0'));
        static::assertFalse(Feature::isActive('PERFORMANCE_TWEAKS'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

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

    public function testIsDoesNotAffectNonPureUnitTests(): void
    {
        $testMethod = '\Shopware\Tests\Integration\Core\BasicOrderProductTest::testBasicOrderFlow';

        $featureFlagConfig = Feature::getRegisteredFeatures();
        $server = $_SERVER;

        $this->extension->executeBeforeTest($testMethod);

        static::assertSame($featureFlagConfig, Feature::getRegisteredFeatures());
        static::assertSame($server, $_SERVER);

        $this->extension->executeAfterTest($testMethod, 0.1);

        static::assertSame($featureFlagConfig, Feature::getRegisteredFeatures());
        static::assertSame($server, $_SERVER);
    }

    public function testFeatureConfigAndEnvIsRestored(): void
    {
        $beforeFeatureFlagConfig = Feature::getRegisteredFeatures();
        $beforeServerEnv = $_SERVER;

        $this->extension->executeBeforeTest(__METHOD__);

        $_SERVER = ['asdf' => 'foo'];
        Feature::resetRegisteredFeatures();
        Feature::registerFeature('foobar');

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertSame($beforeFeatureFlagConfig, Feature::getRegisteredFeatures());
        static::assertSame($beforeServerEnv, $_SERVER);
    }

    /**
     * @ActiveFeatures("FEATURE_NEXT_0000", "v6.4.5.0")
     */
    public function testSetsFeatures(): void
    {
        static::assertArrayNotHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertArrayNotHasKey('V6_4_5_0', $_SERVER);

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertArrayHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertTrue($_SERVER['FEATURE_NEXT_0000']);
        static::assertTrue(Feature::isActive('FEATURE_NEXT_0000'));

        static::assertArrayHasKey('V6_4_5_0', $_SERVER);
        static::assertTrue($_SERVER['V6_4_5_0']);
        static::assertTrue(Feature::isActive('v6.4.5.0'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertArrayNotHasKey('FEATURE_NEXT_0000', $_SERVER);
        static::assertArrayNotHasKey('v6.4.5.0', $_SERVER);
    }
}
