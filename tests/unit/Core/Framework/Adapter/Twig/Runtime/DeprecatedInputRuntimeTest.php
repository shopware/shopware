<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedInputRuntime;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputRuntime::class)]
class DeprecatedInputRuntimeTest extends TestCase
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
        $this->setEnvVars(['V6_8_0_0' => false, 'FEATURE_ALL' => false, 'TESTS_RUNNING' => false]);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
        Feature::$triggerer = $this->triggererBackup;
    }

    public function testTriggersBeforeReturningTheInputValue(): void
    {
        $triggered = false;
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())
            ->method('deprecation')
            ->with('', '', 'deprecated input')
            ->willReturnCallback(static function () use (&$triggered): void {
                $triggered = true;
            });
        Feature::$triggerer = $triggerer;

        $value = DeprecatedInputRuntime::access('v6.8.0.0', 'deprecated input', static function () use (&$triggered): string {
            static::assertTrue($triggered);

            return 'value';
        });

        static::assertSame('value', $value);
    }
}
