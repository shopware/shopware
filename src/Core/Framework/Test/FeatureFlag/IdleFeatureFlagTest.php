<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Finder\Finder;

class IdleFeatureFlagTest extends TestCase
{
    use KernelTestBehaviour;

    public const EXCLUDED_DIRS = [
        'src/Docs',
        'adr',
        'src/Core/Framework/Test/FeatureFlag',
        'src/Administration/Resources/app/administration/node_modules',
        'src/Administration/Resources/app/administration/test/e2e/node_modules',
        'src/Storefront/Resources/app/storefront/node_modules',
        'src/Storefront/Resources/app/storefront/test/e2e/node_modules',
    ];

    public const EXCLUDE_BY_CONTENT = [
        'NEXT-1234',
        'NEXT-12345',
        'NEXT-10286',
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
    ];

    private static $featureAllValue;

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

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->getContainer()->getParameter('shopware.feature.flags'));
    }

    public function testNoIdleFeatureFlagsArePresent(): void
    {
        //init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll());

        // Find the right files to check
        $finder = new Finder();
        $finder->files()
            ->in($this->getContainer()->get('kernel')->getProjectDir() . '/vendor/shopware/*/')
            ->exclude(self::EXCLUDED_DIRS)
            ->notContains(self::EXCLUDE_BY_CONTENT);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $regex = '/FEATURE_NEXT_[0-9]+/';
            preg_match_all($regex, $contents, $keys);
            $availableFlag = array_unique($keys[0]);

            if (!empty($availableFlag)) {
                foreach ($availableFlag as $flag) {
                    static::assertContains($flag, $registeredFlags);
                }
            }
        }
    }
}
