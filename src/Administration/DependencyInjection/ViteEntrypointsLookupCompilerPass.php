<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Pentatrion\ViteBundle\Service\EntrypointsLookup;
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
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === EntrypointsLookup::class) {
                $definition->setShared(false);
            }
        }
    }
}
