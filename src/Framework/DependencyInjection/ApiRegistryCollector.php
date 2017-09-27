<?php declare(strict_types=1);

namespace Shopware\Framework\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ApiRegistryCollector implements CompilerPassInterface
{
    use TagReplaceTrait;

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->setUpRegistry(
            $container,
            'shopware.framework.write.value_transformer_registry',
            'shopware.framework.write.value_transformer'
        );

        $this->setUpRegistry(
            $container,
            'shopware.framework.write.uuid_generator_registry',
            'shopware.framework.write.uuid_generator'
        );

        $this->setUpRegistry(
            $container,
            'shopware.framework.write.filter_registry',
            'shopware.framework.write.filter'
        );
        $this->setUpRegistry(
            $container,
            'shopware.framework.write.resource_registry',
            'shopware.framework.write.resource'
        );
    }

    /**
     * @param ContainerBuilder $container
     * @param $registryId
     * @param $tagName
     *
     * @return Reference
     */
    protected function setUpRegistry(ContainerBuilder $container, string $registryId, string $tagName)
    {
        $registry = $container->findDefinition($registryId);
        $services = $this->findAndSortTaggedServices($tagName, $container);

        foreach ($services as $service) {
            $registry->addArgument($service);
        }
    }
}
