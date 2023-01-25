<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 *
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
#[Package('core')]
class TestKernel extends Kernel
{
    /**
     * @return \Generator<BundleInterface>
     */
    public function registerBundles(): \Generator
    {
        yield from parent::registerBundles();

        yield new TestBundle();
    }
}
