<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class IdleFeatureFlagTest extends TestCase
{
    use KernelTestBehaviour;

    final public const EXCLUDED_DIRS = [
        'Docs',
        'Core/Framework/Test/FeatureFlag',
        'Administration/Resources/app/administration/node_modules',
        'Administration/Resources/app/administration/test/e2e/node_modules',
        'Storefront/Resources/app/storefront/node_modules',
        'Storefront/Resources/app/storefront/test/e2e/node_modules',
    ];

    final public const EXCLUDE_BY_FLAG = [
        'FEATURE_NEXT_1',
        'FEATURE_NEXT_2',
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
        'FEATURE_NEXT_123',
        'FEATURE_NEXT_1234',
        'FEATURE_NEXT_1235',
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
        $platformDir = \dirname(__DIR__, 4);

        // Find the right files to check
        $finder = new Finder();
        $finder->files()
            ->in($platformDir)
            ->exclude(self::EXCLUDED_DIRS);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $regex = '/FEATURE_NEXT_[0-9]+/';
            preg_match_all($regex, $contents, $keys);
            $availableFlag = array_unique($keys[0]);

            if (!empty($availableFlag)) {
                foreach ($availableFlag as $flag) {
                    if (\in_array($flag, self::EXCLUDE_BY_FLAG, true)) {
                        continue;
                    }

                    static::assertContains(
                        $flag,
                        $registeredFlags,
                        sprintf('Found idle feature flag in: %s', $file->getPathname())
                    );
                }
            }
        }
    }
}
