<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

require_once __DIR__ . '/_fixtures/SwagExampleTest/SwagExampleTest.php';
require_once __DIR__ . '/_fixtures/SwagInvalidTest/SwagInvalidTest.php';

use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestKernel extends Kernel
{
    /**
     * @var string
     */
    private $projectDir;

    public function getProjectDir(): string
    {
        if ($this->projectDir === null) {
            $r = new \ReflectionClass($_SERVER['KERNEL_CLASS']);
            $dir = $rootDir = \dirname($r->getFileName());
            while (!file_exists($dir . '/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * This results in the test container, with all private services public
     */
    public function getContainer(): ContainerInterface
    {
        return parent::getContainer();
    }

    protected function initializePlugins(): void
    {
        self::$plugins->add(new \SwagExampleTest\SwagExampleTest());
        self::$plugins->add(new \SwagInvalidTest\SwagInvalidTest());
    }

    protected function initializeFeatureFlags(): void
    {
        //empty body intended, to prevent duplicate registration of feature flags
    }
}
