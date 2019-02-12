<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101Call;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\nextFix102;
use Shopware\Core\Framework\Twig\FeatureFlagExtension;
use Twig_Environment;
use Twig_Loader_Filesystem;

class FeatureTest extends TestCase
{
    /**
     * @var mixed
     */
    private $indicator;

    /**
     * @before
     * @after
     */
    public function cleanup()
    {
        @unlink(__DIR__ . '/_gen/feature_nextTest101.php');
        @unlink(__DIR__ . '/_gen/feature_nextTest123.php');
        @unlink(__DIR__ . '/_gen/feature_nextTest456.php');
        @unlink(__DIR__ . '/_gen/feature_nextTest789.php');
        @unlink(__DIR__ . '/_gen/feature_nextTest123.js');
    }

    /**
     * @before
     */
    public function loadFixture()
    {
        require_once __DIR__ . '/_fixture/feature_nextfix101.php';
        require_once __DIR__ . '/_fixture/feature_nextfix102.php';
    }

    public function testTheGenerator()
    {
        $gen = new FeatureFlagGenerator();

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-123', __DIR__ . '/_gen/');
        $gen->exportJs('NEXT-TEST-123', __DIR__ . '/_gen/');
        self::assertFileExists(__DIR__ . '/_gen/feature_nextTest123.php');
        self::assertFileExists(__DIR__ . '/_gen/feature_nextTest123.js');

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-456', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-789', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-101', __DIR__ . '/_gen/');

        self::assertFalse(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
        include_once __DIR__ . '/_gen/feature_nextTest789.php';
        self::assertTrue(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
    }

    public function testABoolGetsReturned()
    {
        self::assertFalse(nextFix102());
        putenv('FEATURE_NEXT_FIX_102=1');
        self::assertTrue(nextFix102());
    }

    public function testTheCallableGetsExecutes()
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);
        $indicator = false;
        ifNextFix101(function () use (&$indicator) {
            $indicator = true;
        });
        self::assertFalse($indicator);

        putenv(__METHOD__ . '=1');

        ifNextFix101(function () use (&$indicator) {
            $indicator = true;
        });
        self::assertTrue($indicator);
    }

    public function testTheMethodGetsExecutes()
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);
        $this->indicator = null;

        ifNextFix101Call($this, 'indicate');
        self::assertNull($this->indicator);

        putenv(__METHOD__ . '=1');

        ifNextFix101Call($this, 'indicate', new \stdClass());
        self::assertInstanceOf(\stdClass::class, $this->indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState()
    {
        $currentConfig = FeatureConfig::getAll();

        self::assertArrayNotHasKey(__METHOD__, $currentConfig);
        FeatureConfig::registerFlag(__METHOD__, __METHOD__);

        $configAfterRegistration = FeatureConfig::getAll();
        self::assertFalse($configAfterRegistration[__METHOD__]);

        putenv(__METHOD__ . '=1');
        $activatedFlagConfig = FeatureConfig::getAll();
        self::assertTrue($activatedFlagConfig[__METHOD__]);
    }

    public function testTwigFeatureFlag()
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);

        $loader = new Twig_Loader_Filesystem(__DIR__ . '/_fixture/');
        $twig = new Twig_Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest.html.twig');
        putenv(__METHOD__ . '=1');
        self::assertSame('FeatureIsActive', $template->render([]));
        putenv(__METHOD__ . '=0');
        self::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegistered()
    {
        $loader = new Twig_Loader_Filesystem(__DIR__ . '/_fixture/');
        $twig = new Twig_Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest_unregistered.html.twig');

        $this->expectException(\Twig_Error_Runtime::class);
        $this->expectExceptionMessageRegExp('/.*randomFlagThatIsNotRegisterde471112.*/');
        $template->render([]);
    }

    private function indicate(\stdClass $arg)
    {
        $this->indicator = $arg;
    }
}
