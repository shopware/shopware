<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(DeprecatedAlias::class)]
class DeprecatedAliasTest extends TestCase
{
    use EnvTestBehaviour;

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    private ?Triggerer $triggererBackup;

    protected function setUp(): void
    {
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
        $this->triggererBackup = Feature::$triggerer;

        Feature::resetRegisteredFeatures();
        Feature::registerFeature('v6.8.0.0', ['major' => true]);
        Feature::$emitDeprecations = true;

        $this->setEnvVars([
            'V6_8_0_0' => false,
            'FEATURE_ALL' => false,
            'TESTS_RUNNING' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
        Feature::$triggerer = $this->triggererBackup;
        Feature::$emitDeprecations = true;
    }

    public function testPassesThroughRegularValues(): void
    {
        static::assertSame('value', DeprecatedAlias::resolve('value', 'type'));
    }

    public function testTriggersDeprecationBeforeReturningAliasValue(): void
    {
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', 'The "type" Twig variable is deprecated.');
        Feature::$triggerer = $triggerer;

        $alias = new DeprecatedAlias('billing');

        static::assertSame('billing', DeprecatedAlias::resolve($alias, 'type'));
    }

    public function testActiveRemovalFeatureRejectsAliasAccess(): void
    {
        $this->setEnvVars(['V6_8_0_0' => true]);

        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: The "type" Twig variable is deprecated.'));

        DeprecatedAlias::resolve(new DeprecatedAlias('billing'), 'type');
    }
}
