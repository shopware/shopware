<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\FeatureCallOptimizerNodeVisitor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(FeatureFlagExtension::class)]
class FeatureFlagExtensionTest extends TestCase
{
    use EnvTestBehaviour;

    private const OPTIMIZATION_FLAG = 'TWIG_COMPILE_TIME_OPTIMIZATION';

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $registeredFeaturesBackup;

    /**
     * @var list<string>
     */
    private array $fixtureFlags = [
        'FEATURE_NEXT_101',
    ];

    protected function setUp(): void
    {
        $this->registeredFeaturesBackup = Feature::getRegisteredFeatures();

        $this->setEnvVars([
            'APP_ENV' => 'test',
            'FEATURE_ALL' => 'false',
            self::OPTIMIZATION_FLAG => 'false',
        ]);

        $this->unsetFixtureEnv();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->registeredFeaturesBackup);
    }

    protected function tearDown(): void
    {
        $this->unsetFixtureEnv();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->registeredFeaturesBackup);
    }

    public function testDoesNotRegisterVisitorWhenOptimizationIsInactive(): void
    {
        $this->registerTwigOptimizationFlag();

        static::assertSame([], (new FeatureFlagExtension())->getNodeVisitors());
    }

    public function testRegistersVisitorWhenOptimizationIsActive(): void
    {
        $this->registerTwigOptimizationFlag();
        $this->setEnvVars([self::OPTIMIZATION_FLAG => '1']);

        $nodeVisitors = (new FeatureFlagExtension())->getNodeVisitors();

        static::assertCount(1, $nodeVisitors);
        static::assertInstanceOf(FeatureCallOptimizerNodeVisitor::class, $nodeVisitors[0]);
    }

    public function testTwigFeatureFlag(): void
    {
        $this->setUpFixtures();
        $this->registerTwigOptimizationFlag();
        $loader = new FilesystemLoader(__DIR__ . '/fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest.html.twig'), 'featuretest.html.twig');
        $this->setEnvVars(['FEATURE_NEXT_101' => '1']);
        static::assertSame('FeatureIsActive', $template->render([]));
        $this->setEnvVars(['FEATURE_NEXT_101' => '0']);
        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegistered(): void
    {
        $this->registerTwigOptimizationFlag();
        $this->setEnvVars(['APP_ENV' => 'test']);

        $loader = new FilesystemLoader(__DIR__ . '/fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegisteredInProd(): void
    {
        $this->registerTwigOptimizationFlag();
        $this->setEnvVars(['APP_ENV' => 'prod']);

        $loader = new FilesystemLoader(__DIR__ . '/fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    private function unsetFixtureEnv(): void
    {
        foreach ($this->fixtureFlags as $flag) {
            unset($_SERVER[$flag], $_ENV[$flag]);
            putenv($flag);
        }
    }

    private function registerTwigOptimizationFlag(): void
    {
        if (Feature::has(self::OPTIMIZATION_FLAG)) {
            return;
        }

        Feature::registerFeature(self::OPTIMIZATION_FLAG, ['default' => false]);
    }

    private function setUpFixtures(): void
    {
        // init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll(false));
        $registeredFlags = array_merge($registeredFlags, $this->fixtureFlags);

        Feature::registerFeatures($registeredFlags);
    }
}
