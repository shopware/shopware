<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\FeatureFlagExtension;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101Call;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\nextFix102;

class FeatureTest extends TestCase
{
    private $indicator;

    /**
     * @before
     * @after
     */
    public function cleanup(): void
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
    public function loadFixture(): void
    {
        require_once __DIR__ . '/_fixture/feature_nextfix101.php';
        require_once __DIR__ . '/_fixture/feature_nextfix102.php';
    }

    public function testTheGenerator(): void
    {
        $gen = new FeatureFlagGenerator();

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-123', __DIR__ . '/_gen/');
        $gen->exportJs('NEXT-TEST-123', __DIR__ . '/_gen/');
        static::assertFileExists(__DIR__ . '/_gen/feature_nextTest123.php');
        static::assertFileExists(__DIR__ . '/_gen/feature_nextTest123.js');

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-456', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-789', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-101', __DIR__ . '/_gen/');

        static::assertFalse(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
        include_once __DIR__ . '/_gen/feature_nextTest789.php';
        static::assertTrue(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
    }

    public function testABoolGetsReturned(): void
    {
        static::assertFalse(nextFix102());
        $_SERVER['FEATURE_NEXT_FIX_102'] = '1';
        static::assertTrue(nextFix102());
    }

    public function testTheCallableGetsExecutes(): void
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);
        $indicator = false;
        ifNextFix101(function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertFalse($indicator);

        $_SERVER[__METHOD__] = '1';

        ifNextFix101(function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertTrue($indicator);
    }

    public function testTheMethodGetsExecutes(): void
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);
        $this->indicator = null;

        ifNextFix101Call($this, 'indicate');
        static::assertNull($this->indicator);

        $_SERVER[__METHOD__] = '1';

        ifNextFix101Call($this, 'indicate', new \stdClass());
        static::assertInstanceOf(\stdClass::class, $this->indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState(): void
    {
        $currentConfig = FeatureConfig::getAll();

        static::assertArrayNotHasKey(__METHOD__, $currentConfig);
        FeatureConfig::registerFlag(__METHOD__, __METHOD__);

        $configAfterRegistration = FeatureConfig::getAll();
        static::assertFalse($configAfterRegistration[__METHOD__]);

        $_SERVER[__METHOD__] = '1';
        $activatedFlagConfig = FeatureConfig::getAll();
        static::assertTrue($activatedFlagConfig[__METHOD__]);
    }

    public function testTwigFeatureFlag(): void
    {
        FeatureConfig::registerFlag('nextFix101', __METHOD__);

        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest.html.twig');
        $_SERVER[__METHOD__] = '1';
        static::assertSame('FeatureIsActive', $template->render([]));
        $_SERVER[__METHOD__] = '0';
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

    private function indicate(\stdClass $arg): void
    {
        $this->indicator = $arg;
    }
}
