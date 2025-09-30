<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 *
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class MockedKernel extends Kernel
{
    /**
     * @param array<string, BundleInterface> $bundles
     */
    public function __construct(array $bundles, ?KernelPluginLoader $pluginLoader = null)
    {
        $this->bundles = $bundles;

        if ($pluginLoader) {
            $this->pluginLoader = $pluginLoader;
        }
    }
}
