<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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

    public function testAllFeatureFlagsAreActivated(): void
    {
        $_SERVER['V6_5_0_0'] = false;
        $_SERVER['PERFORMANCE_TWEAKS'] = false;

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertTrue(Feature::isActive('v6.5.0.0'));
        static::assertTrue(Feature::isActive('PERFORMANCE_TWEAKS'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertArrayHasKey('V6_5_0_0', $_SERVER);
        static::assertFalse($_SERVER['V6_5_0_0']);

        static::assertArrayHasKey('PERFORMANCE_TWEAKS', $_SERVER);
        static::assertFalse($_SERVER['PERFORMANCE_TWEAKS']);
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
     * @DisabledFeatures(features={"v6.5.0.0", "PERFORMANCE_TWEAKS"})
     */
    public function testSetsFeatures(): void
    {
        static::assertArrayNotHasKey('V6_5_0_0', $_SERVER);
        static::assertArrayNotHasKey('PERFORMANCE_TWEAKS', $_SERVER);

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertArrayHasKey('V6_5_0_0', $_SERVER);
        static::assertFalse($_SERVER['V6_5_0_0']);
        static::assertFalse(Feature::isActive('v6.5.0.0'));

        static::assertArrayHasKey('PERFORMANCE_TWEAKS', $_SERVER);
        static::assertFalse($_SERVER['PERFORMANCE_TWEAKS']);
        static::assertFalse(Feature::isActive('PERFORMANCE_TWEAKS'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertArrayNotHasKey('V6_5_0_0', $_SERVER);
        static::assertArrayNotHasKey('PERFORMANCE_TWEAKS', $_SERVER);
    }
}
