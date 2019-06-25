<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

trait KernelTestBehaviour
{
    protected function getKernel(): KernelInterface
    {
        return KernelLifecycleManager::getKernel();
    }

    /**
     * This results in the test container, with all private services public
     */
    protected function getContainer(): ContainerInterface
    {
        $container = $this->getKernel()->getContainer();

        if (!$container->has('test.service_container')) {
            throw new \RuntimeException('Unable to run tests against kernel without test.service_container');
        }

        return $container->get('test.service_container');
    }
}
