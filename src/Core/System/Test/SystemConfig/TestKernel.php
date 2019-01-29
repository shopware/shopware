<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig;

require_once __DIR__ . '/_fixtures/SwagExampleTest/SwagExampleTest.php';
require_once __DIR__ . '/_fixtures/SwagInvalidTest/SwagInvalidTest.php';

use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestKernel extends Kernel
{
    public function getProjectDir()
    {
        return __DIR__ . '/../../../../../../../../';
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
