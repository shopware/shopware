<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait KernelTestBehaviour
{
    use EventDispatcherBehaviour;

    protected static function getKernel(): Kernel
    {
        return KernelLifecycleManager::getKernel();
    }

    /**
     * This results in the test container, with all private services public
     */
    protected static function getContainer(): ContainerInterface
    {
        $container = static::getKernel()->getContainer();

        if (!$container->has('test.service_container')) {
            throw new \RuntimeException('Unable to run tests against kernel without test.service_container');
        }

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->get('test.service_container');

        return $testContainer;
    }
}
