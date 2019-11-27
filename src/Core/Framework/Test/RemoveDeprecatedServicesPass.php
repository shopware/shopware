<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveDeprecatedServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isDeprecated()) {
                $container->removeDefinition($id);
            }
        }
    }
}
