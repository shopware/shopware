<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlagExtension;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 *
 * @covers \Shopware\Core\Test\PHPUnit\Extension\FeatureFlagExtension
 */
class FeatureFlagExtensionTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $serverVarsBackup;

    /**
     * @var array<string, mixed>
     */
    private array $envVarsBackup;

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    private FeatureFlagExtension $extension;

    protected function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
        $this->extension = new FeatureFlagExtension('Shopware\\Tests\\Unit\\', true);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testAllFeatureFlagsAreActivated(): void
    {
        $_SERVER['V6_5_0_0'] = false;

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertTrue(Feature::isActive('v6.5.0.0'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertArrayHasKey('V6_5_0_0', $_SERVER);
        static::assertFalse($_SERVER['V6_5_0_0']);
    }

    public function testIsDoesNotAffectNonPureUnitTests(): void
    {
        $testMethod = '\Shopware\Tests\Integration\Core\Checkout\BasicOrderProductTest::testBasicOrderFlow';

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

    #[DisabledFeatures(['v6.5.0.0'])]
    public function testSetsFeatures(): void
    {
        static::assertArrayNotHasKey('V6_5_0_0', $_SERVER);

        $this->extension->executeBeforeTest(__METHOD__);

        static::assertArrayHasKey('V6_5_0_0', $_SERVER);
        static::assertFalse($_SERVER['V6_5_0_0']);
        static::assertFalse(Feature::isActive('v6.5.0.0'));

        $this->extension->executeAfterTest(__METHOD__, 0.1);

        static::assertArrayNotHasKey('V6_5_0_0', $_SERVER);
    }
}
