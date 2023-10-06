<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Files;

use Shopware\Core\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @method void configureContainer(ContainerBuilder $container, LoaderInterface $loader)
 */
class MockedKernel extends Kernel
{
    public function __construct(array $bundles)
    {
        $this->bundles = $bundles;
    }
}
