<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * An entrypoints lookup keeps the entry point URLs of its first use and has no reset method.
 * Those URLs contain the asset URL, which can depend on the current request, so long-running workers need a fresh lookup per use.
 * The file content stays cached in the ViteFileAccessorDecorator.
 *
 * @internal
 */
#[Package('framework')]
class ViteEntrypointsLookupCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('pentatrion_vite.configs')) {
            return;
        }

        $configs = $container->getParameter('pentatrion_vite.configs');
        \assert(\is_array($configs));

        foreach (array_keys($configs) as $configName) {
            $container->getDefinition('pentatrion_vite.entrypoints_lookup[' . $configName . ']')->setShared(false);
        }
    }
}
