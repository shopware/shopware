<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TwigLoaderConfigCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $fileSystemLoader = $container->findDefinition('twig.loader.native_filesystem');

        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            $directory = $bundle['path'] . '/Resources/views';
            if (!file_exists($directory)) {
                continue;
            }

            $fileSystemLoader->addMethodCall('addPath', [$directory]);
            $fileSystemLoader->addMethodCall('addPath', [$directory, $name]);
        }
    }
}
