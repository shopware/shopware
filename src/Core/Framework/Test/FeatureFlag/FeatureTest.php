<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FeatureFlag\FeatureConfig;
use Shopware\Core\Framework\FeatureFlag\FeatureFlagGenerator;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\ifNextFix101Call;
use function Shopware\Core\Framework\Test\FeatureFlag\_fixture\nextFix101;

class FeatureTest extends TestCase
{
    /**
     * @before
     * @after
     */
    public function cleanup()
    {
        @unlink(__DIR__ . '/features.php');
        @unlink(__DIR__ . '/_gen/feature_nexttest101.php');
        @unlink(__DIR__ . '/_gen/feature_nexttest123.php');
        @unlink(__DIR__ . '/_gen/feature_nexttest456.php');
        @unlink(__DIR__ . '/_gen/feature_nexttest789.php');
    }

    /**
     * @before
     */
    public function loadFixture()
    {
        require_once __DIR__ . '/_fixture/feature_nextfix101.php';
    }

    /**
     * @var bool
     */
    private $indicator;

    public function test_the_generator()
    {
        $gen = new FeatureFlagGenerator();

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-123', __DIR__ . '/_gen/');
        self::assertFileExists(__DIR__ . '/_gen/feature_nexttest123.php');

        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-456', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-789', __DIR__ . '/_gen/');
        $gen->exportPhp('Shopware\Core\Framework\Test\FeatureFlag\_gen', 'NEXT-TEST-101', __DIR__ . '/_gen/');

        self::assertFalse(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
        include_once __DIR__ . '/_gen/feature_nexttest789.php';
        self::assertTrue(function_exists('Shopware\Core\Framework\Test\FeatureFlag\_gen\ifNextTest789Call'));
    }

    public function test_the_bool_gets_returned()
    {
        self::assertFalse(nextFix101());
        FeatureConfig::activate('nextFix101');
        self::assertTrue(nextFix101());
    }

    public function test_the_callable_gets_executes()
    {
        FeatureConfig::addFlag('nextFix101');
        $indicator = false;
        ifNextFix101(function() use (&$indicator) {
            $indicator = true;
        });
        self::assertFalse($indicator);

        FeatureConfig::activate('nextFix101');

        ifNextFix101(function() use (&$indicator) {
            $indicator = true;
        });
        self::assertTrue($indicator);
    }

    public function test_the_method_gets_executes()
    {
        FeatureConfig::addFlag('nextFix101');
        $this->indicator = false;

        ifNextFix101Call($this, 'indicate');
        self::assertFalse($this->indicator);

        FeatureConfig::activate('nextFix101');

        ifNextFix101Call($this, 'indicate');
        self::assertTrue($this->indicator);
    }

    private function indicate() {
        $this->indicator = true;
    }
}
