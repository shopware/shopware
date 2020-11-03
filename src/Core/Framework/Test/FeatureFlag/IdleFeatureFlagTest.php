<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Finder\Finder;

class IdleFeatureFlagTest extends TestCase
{
    use KernelTestBehaviour;

    public static $featureAllValue;

    public $excludedDirs = [
        'src/Docs',
        'adr',
        'src/Core/Framework/Test/FeatureFlag',
        'src/Administration/Resources/app/administration/node_modules',
        'src/Administration/Resources/app/administration/test/e2e/node_modules',
        'src/Storefront/Resources/app/storefront/node_modules',
        'src/Storefront/Resources/app/storefront/test/e2e/node_modules'
    ];

    public $excludeByContent = [
        'NEXT-1234',
        'NEXT-12345',
        'NEXT-10286'
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

    public function testNoIdleFeatureFlagsArePresent(): void
    {
        //init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll());
        $availableFeatureFlags = [];

        // Find the right files to check
        $finder = new Finder();
        $finder->files()
            ->in($this->getContainer()->get('kernel')->getProjectDir() . '/vendor/shopware/platform')
            ->exclude($this->excludedDirs)
            ->notContains($this->excludeByContent);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $regex = '/FEATURE_NEXT_[0-9]+/';
            preg_match($regex, $contents, $keys);

            if (isset($keys[0])) {
                $availableFeatureFlags[] = $keys[0];
            }
        }
        $availableFeatureFlags = array_unique($availableFeatureFlags);

        foreach ($availableFeatureFlags as $flag) {
            static::assertContains($flag, $registeredFlags);
        }
    }
}
