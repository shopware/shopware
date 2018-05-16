<?php declare(strict_types=1);

namespace Shopware\Framework\DependencyInjection\CompilerPass;

use Shopware\Framework\ORM\DefinitionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DefinitionRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $this->collectDefinitions($container);
    }

    private function getEntity(array $attributes)
    {
        foreach ($attributes as $attr) {
            if (array_key_exists('entity', $attr)) {
                return $attr['entity'];
            }
        }

        return null;
    }

    /**
     * @param ContainerBuilder $container
     */
    private function collectDefinitions(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.entity.definition');

        $classes = [];
        foreach ($services as $serviceId => $attributes) {
            $service = $container->getDefinition($serviceId);
            $entity = $this->getEntity($attributes);
            if ($entity === null) {
                continue;
            }
            $classes[$entity] = $service->getClass();
        }

        $registry = $container->getDefinition(DefinitionRegistry::class);
        $registry->replaceArgument(0, $classes);
    }
}
