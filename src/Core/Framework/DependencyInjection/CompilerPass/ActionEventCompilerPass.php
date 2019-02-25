<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Event\ActionEventRegistry;
use Shopware\Core\Framework\Event\ActionEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ActionEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $reflectionClass = new \ReflectionClass(ActionEvents::class);
        $constants = array_values($reflectionClass->getConstants());

        $definition = $container->getDefinition(ActionEventRegistry::class);
        $definition->addMethodCall('add', $constants);
    }
}
