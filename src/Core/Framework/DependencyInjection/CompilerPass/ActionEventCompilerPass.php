<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\BusinessEvents;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ActionEventCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $reflectionClass = new \ReflectionClass(BusinessEvents::class);
        $constants = array_values($reflectionClass->getConstants());

        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('add', $constants);
    }
}
