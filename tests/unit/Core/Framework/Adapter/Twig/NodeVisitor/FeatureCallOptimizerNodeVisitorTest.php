<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\FeatureCallOptimizerNodeVisitor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[CoversClass(FeatureCallOptimizerNodeVisitor::class)]
#[CoversClass(FeatureFlagExtension::class)]
class FeatureCallOptimizerNodeVisitorTest extends TestCase
{
    use EnvTestBehaviour;

    private const OPTIMIZATION_FLAG = 'TWIG_COMPILE_TIME_OPTIMIZATION';
    private const ACTIVE_FEATURE = 'FEATURE_ACTIVE';
    private const INACTIVE_FEATURE = 'FEATURE_INACTIVE';

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    private static int $templateCounter = 0;

    protected function setUp(): void
    {
        $this->featureConfigBackup = Feature::getRegisteredFeatures();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            self::OPTIMIZATION_FLAG => ['default' => false],
            self::ACTIVE_FEATURE => ['default' => false],
            self::INACTIVE_FEATURE => ['default' => false],
        ]);

        $this->setEnvVars([
            self::OPTIMIZATION_FLAG => false,
            self::ACTIVE_FEATURE => false,
            self::INACTIVE_FEATURE => false,
            'FEATURE_ALL' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testFeatureFlagExtensionDoesNotRegisterVisitorWhenOptimizationIsInactive(): void
    {
        static::assertSame([], (new FeatureFlagExtension())->getNodeVisitors());
    }

    public function testFeatureFlagExtensionRegistersVisitorWhenOptimizationIsActive(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);

        $nodeVisitors = (new FeatureFlagExtension())->getNodeVisitors();

        static::assertCount(1, $nodeVisitors);
        static::assertInstanceOf(FeatureCallOptimizerNodeVisitor::class, $nodeVisitors[0]);
    }

    public function testStaticFeatureCallStaysRuntimeCheckWhenOptimizationIsInactive(): void
    {
        $this->setFeature(self::ACTIVE_FEATURE, true);

        $template = $this->createTwig('{% if feature("FEATURE_ACTIVE") %}active{% else %}inactive{% endif %}')
            ->load('index.html.twig');

        static::assertSame('active', $template->render([]));

        $this->setFeature(self::ACTIVE_FEATURE, false);

        static::assertSame('inactive', $template->render([]));
    }

    public function testStaticFeatureCallIsResolvedWhenOptimizationIsActive(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);
        $this->setFeature(self::ACTIVE_FEATURE, true);

        $template = $this->createTwig('{% if feature("FEATURE_ACTIVE") %}active{% else %}inactive{% endif %}')
            ->load('index.html.twig');

        static::assertSame('active', $template->render([]));

        $this->setFeature(self::ACTIVE_FEATURE, false);

        static::assertSame('active', $template->render([]));
    }

    public function testInactiveFeatureCallIsReplacedByElseBranch(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);

        $output = $this->render('{% if feature("FEATURE_INACTIVE") %}active{% else %}inactive{% endif %}');

        static::assertSame('inactive', $output);
    }

    public function testNegatedFeatureCallIsOptimized(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);

        $output = $this->render('{% if not feature("FEATURE_INACTIVE") %}inactive{% else %}active{% endif %}');

        static::assertSame('inactive', $output);
    }

    public function testAndExpressionIsOptimizedToRemainingDynamicExpression(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);
        $this->setFeature(self::ACTIVE_FEATURE, true);

        $twig = $this->createTwig('{% if feature("FEATURE_ACTIVE") and enabled %}active{% else %}inactive{% endif %}');
        $template = $twig->load('index.html.twig');

        static::assertSame('active', $template->render(['enabled' => true]));
        static::assertSame('inactive', $template->render(['enabled' => false]));
    }

    public function testOrExpressionIsOptimizedToRemainingDynamicExpression(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);

        $twig = $this->createTwig('{% if feature("FEATURE_INACTIVE") or enabled %}active{% else %}inactive{% endif %}');
        $template = $twig->load('index.html.twig');

        static::assertSame('active', $template->render(['enabled' => true]));
        static::assertSame('inactive', $template->render(['enabled' => false]));
    }

    public function testFalseFeatureBranchKeepsElseIfConditions(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);

        $twig = $this->createTwig('{% if feature("FEATURE_INACTIVE") %}feature{% elseif enabled %}elseif{% else %}else{% endif %}');
        $template = $twig->load('index.html.twig');

        static::assertSame('elseif', $template->render(['enabled' => true]));
        static::assertSame('else', $template->render(['enabled' => false]));
    }

    public function testDynamicFeatureNameIsNotOptimized(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);
        $this->setFeature(self::ACTIVE_FEATURE, true);

        $template = $this->createTwig('{% set flag = "FEATURE_ACTIVE" %}{% if feature(flag) %}active{% else %}inactive{% endif %}')
            ->load('index.html.twig');

        static::assertSame('active', $template->render([]));

        $this->setFeature(self::ACTIVE_FEATURE, false);

        static::assertSame('inactive', $template->render([]));
    }

    public function testUnregisteredFeatureCallIsNotOptimized(): void
    {
        $this->setFeature(self::OPTIMIZATION_FLAG, true);
        $this->setEnvVars(['APP_ENV' => 'test']);

        $compileErrors = 0;
        set_error_handler(static function () use (&$compileErrors): bool {
            ++$compileErrors;

            return true;
        }, \E_USER_WARNING);
        $template = null;

        try {
            $template = $this->createTwig('{% if feature("UNREGISTERED_FEATURE_FLAG") %}active{% else %}inactive{% endif %}')
                ->load('index.html.twig');
        } finally {
            restore_error_handler();
        }

        static::assertSame(0, $compileErrors, 'No warning should be triggered during template compilation for unregistered features');

        $renderErrors = 0;
        set_error_handler(static function () use (&$renderErrors): bool {
            ++$renderErrors;

            return true;
        }, \E_USER_WARNING);

        try {
            static::assertSame('inactive', $template->render([]));
        } finally {
            restore_error_handler();
        }

        static::assertSame(0, $renderErrors, 'Unregistered feature should be handled by the runtime guard without triggering a warning');

        Feature::registerFeature('UNREGISTERED_FEATURE_FLAG', ['default' => true]);

        static::assertSame('active', $template->render([]), 'Unregistered feature calls should not be optimized during compilation');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context = []): string
    {
        return $this->createTwig($template)->render('index.html.twig', $context);
    }

    private function createTwig(string $template): Environment
    {
        $template .= \sprintf('{# %d #}', ++self::$templateCounter);

        $twig = new Environment(new ArrayLoader(['index.html.twig' => $template]), [
            'cache' => false,
            'strict_variables' => true,
        ]);
        $twig->addExtension(new FeatureFlagExtension());

        return $twig;
    }

    private function setFeature(string $feature, bool $active): void
    {
        $this->setEnvVars([
            $feature => $active ? '1' : 'false',
        ]);
    }
}
