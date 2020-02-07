<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use Composer\Autoload\ClassMapGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\FeatureFlagExtension;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use Shopware\Core\Framework\Test\FeatureFlag\_fixture\FEATURE_NEXT_FIX_101;
use Shopware\Core\Framework\Test\FeatureFlag\_fixture\FEATURE_NEXT_FIX_102;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;

class FeatureTest extends TestCase
{
    use KernelTestBehaviour;

    private $indicator;

    protected function setUp(): void
    {
        //init FeatureConfig
        FeatureConfig::addFeatureFlagPaths(__DIR__ . '/_fixture/');
    }

    /**
     * @before
     * @after
     */
    public function cleanup(): void
    {
        @unlink(__DIR__ . '/_gen/FEATURE_NEXT_TEST_123.php');
        @unlink(__DIR__ . '/_gen/FEATURE_NEXT_TEST_789.php');
        @unlink(__DIR__ . '/_gen/FEATURE_NEXT_TEST_101.php');
        @unlink(__DIR__ . '/_gen/FEATURE_NEXT_TEST_456.php');
    }

    public function testTheGenerator(): void
    {
        $gen = new FeatureFlagGenerator();

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-123', __DIR__ . '/_gen/');
        static::assertFileExists(__DIR__ . '/_gen/FEATURE_NEXT_TEST_123.php');

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-456', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-101', __DIR__ . '/_gen/');

        static::assertFalse(class_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\FEATURE_NEXT_TEST_789'));
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-789', __DIR__ . '/_gen/');
        include_once __DIR__ . '/_gen/FEATURE_NEXT_TEST_789.php';
        static::assertTrue(class_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\FEATURE_NEXT_TEST_789'));
    }

    public function testABoolGetsReturned(): void
    {
        static::assertFalse(FeatureConfig::isActive(FEATURE_NEXT_FIX_102::NAME));
        $_SERVER['FEATURE_NEXT_FIX_102'] = '1';
        static::assertTrue(FeatureConfig::isActive(FEATURE_NEXT_FIX_102::NAME));
    }

    public function testTheCallableGetsExecutes(): void
    {
        $_SERVER[FEATURE_NEXT_FIX_101::NAME] = '0';
        $indicator = false;
        FeatureConfig::ifActive(FEATURE_NEXT_FIX_101::NAME, function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertFalse($indicator);

        $_SERVER[FEATURE_NEXT_FIX_101::NAME] = '1';

        FeatureConfig::ifActive(FEATURE_NEXT_FIX_101::NAME, function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertTrue($indicator);
    }

    public function testTheMethodGetsExecutes(): void
    {
        $this->indicator = null;

        FeatureConfig::ifActiveCall(FEATURE_NEXT_FIX_101::NAME, $this, 'indicate');
        static::assertNull($this->indicator);

        $_SERVER[FEATURE_NEXT_FIX_101::NAME] = '1';

        FeatureConfig::ifActiveCall(FEATURE_NEXT_FIX_101::NAME, $this, 'indicate', new \stdClass());
        static::assertInstanceOf(\stdClass::class, $this->indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState(): void
    {
        FeatureConfig::removeFeatureFlagPaths(__DIR__ . '/_fixture/');
        $currentConfig = array_keys(FeatureConfig::getAll());
        $map = array_keys(ClassMapGenerator::createMap(__DIR__ . '/../../../Flag/Flags'));
        $featureFlags = [];
        foreach ($map as $featureClass) {
            $featureFlags[] = $featureClass::NAME;
        }

        static::assertEquals($featureFlags, $currentConfig);

        FeatureConfig::addFeatureFlagPaths(__DIR__ . '/_fixture/');
        $map = array_keys(ClassMapGenerator::createMap(__DIR__ . '/_fixture/'));
        foreach ($map as $featureClass) {
            $featureFlags[] = $featureClass::NAME;
        }

        $configAfterRegistration = array_keys(FeatureConfig::getAll());
        static::assertEquals($featureFlags, $configAfterRegistration);
    }

    public function testTwigFeatureFlag(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest.html.twig');
        $_SERVER[FEATURE_NEXT_FIX_101::NAME] = '1';
        static::assertSame('FeatureIsActive', $template->render([]));
        $_SERVER[FEATURE_NEXT_FIX_101::NAME] = '0';
        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegistered(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest_unregistered.html.twig');

        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessageRegExp('/.*randomFlagThatIsNotRegisterde471112.*/');
        $template->render([]);
    }

    private function indicate(?\stdClass $arg = null): void
    {
        $this->indicator = $arg;
    }
}
