<?php

namespace script\boot;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class ScriptKernel extends \Shopware\Core\Kernel
{
    protected function build(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract()) {
                continue;
            }
            $definition->setPublic(true);
        }

        return $container;
    }
}
