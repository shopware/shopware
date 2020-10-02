<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\FeatureFlagExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FeatureTest extends TestCase
{
    use KernelTestBehaviour;

    public static $featureAllValue;

    private $indicator;

    private $fixtureFlags = [
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
    ];

    public static function setUpBeforeClass(): void
    {
        self::$featureAllValue = $_SERVER['FEATURE_ALL'] ?? 'false';
    }

    public static function tearDownAfterClass(): void
    {
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'] = self::$featureAllValue;
    }

    protected function setUp(): void
    {
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'] = 'false';
        $_SERVER['APP_ENV'] = 'test';
        Feature::setRegisteredFeatures(
            $this->getContainer()->getParameter('shopware.feature.flags'),
            $this->getContainer()->getParameter('kernel.cache_dir') . '/shopware_features.php'
        );
    }

    public function testABoolGetsReturned(): void
    {
        $this->setUpFixtures();
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));
    }

    public function testTheCallableGetsExecutes(): void
    {
        $this->setUpFixtures();
        $_SERVER['FEATURE_NEXT_101'] = '0';
        $indicator = false;
        Feature::ifActive('FEATURE_NEXT_101', function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertFalse($indicator);

        $_SERVER['FEATURE_NEXT_101'] = '1';

        Feature::ifActive('FEATURE_NEXT_101', function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertTrue($indicator);
    }

    public function testTheMethodGetsExecutes(): void
    {
        $this->setUpFixtures();
        $this->indicator = null;

        Feature::ifActiveCall('FEATURE_NEXT_101', $this, 'indicate');
        static::assertNull($this->indicator);

        $_SERVER['FEATURE_NEXT_101'] = '1';

        Feature::ifActiveCall('FEATURE_NEXT_101', $this, 'indicate', new \stdClass());
        static::assertInstanceOf(\stdClass::class, $this->indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState(): void
    {
        $this->setUp();
        $currentConfig = array_keys(Feature::getAll());
        $featureFlags = $this->getContainer()->getParameter('shopware.feature.flags');

        foreach ($featureFlags as &$flag) {
            $flag = Feature::normalizeName($flag);
        }

        static::assertEquals($featureFlags, $currentConfig);

        self::setUpFixtures();
        $featureFlags = array_merge($featureFlags, $this->fixtureFlags);

        $configAfterRegistration = array_keys(Feature::getAll());
        static::assertEquals($featureFlags, $configAfterRegistration);
    }

    public function testTwigFeatureFlag(): void
    {
        self::setUpFixtures();
        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate('featuretest.html.twig');
        $_SERVER['FEATURE_NEXT_101'] = '1';
        static::assertSame('FeatureIsActive', $template->render([]));
        $_SERVER['FEATURE_NEXT_101'] = '0';
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

        if ($_SERVER['APP_ENV'] !== 'prod') {
            $this->expectNoticeMessageMatches('/.*FEATURE_RANDOMFLAGTHATISNOTREGISTERDE_471112.*/');
        } else {
            static::assertTrue(true, 'No Notice in prod mode');
        }
        $template->render([]);
    }

    private function setUpFixtures(): void
    {
        //init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll());
        $registeredFlags = array_merge($registeredFlags, $this->fixtureFlags);

        Feature::setRegisteredFeatures(
            $registeredFlags,
            $this->getContainer()->getParameter('kernel.cache_dir') . '/shopware_features.php'
        );
    }

    private function indicate(?\stdClass $arg = null): void
    {
        $this->indicator = $arg;
    }
}
