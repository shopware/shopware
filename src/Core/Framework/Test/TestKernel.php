<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class TestKernel extends Kernel
{
    /**
     * Returns an array of bundles to register.
     *
     * @return iterable|BundleInterface[] An iterable of bundle instances
     */
    public function registerBundles()
    {
        yield from parent::registerBundles();

        yield new TestBundle();
    }

    public function getProjectDir()
    {
        return __DIR__ . '/../../../../../';
    }
}
