<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class EntityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
        $this->makeFieldSerializersPublic($container);
        $this->makeFieldResolversPublic($container);
        $this->makeFieldAccessorBuildersPublic($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $entityNameMap = [];
        $repositoryNameMap = [];
        $services = $container->findTaggedServiceIds('shopware.entity.definition');

        /** @var string $serviceId */
        foreach ($services as $serviceId => $tag) {
            $service = $container->getDefinition($serviceId);

            $service->addMethodCall('compile', [
                new Reference(DefinitionInstanceRegistry::class),
            ]);
            $service->setPublic(true);

            /** @var string $class */
            $class = $service->getClass();
            /** @var EntityDefinition $instance */
            $instance = new $class();

            $entityNameMap[$instance->getEntityName()] = $serviceId;
            $entity = $instance->getEntityName();
            $fallBackEntity = $tag[0]['entity'] ?? null;

            $repositoryId = $instance->getEntityName() . '.repository';
            $fallBackRepositoryId = null;

            /*
             * @deprecated tag:v6.3.0 use getEntityName instead
             */
            if ($entity !== null && $entity !== $fallBackEntity) {
                $entityNameMap[$fallBackEntity] = $serviceId;
                $fallBackRepositoryId = $fallBackEntity . '.repository';
            }

            try {
                $container->getDefinition($repositoryId);

                if ($fallBackRepositoryId) {
                    $container->getDefinition($fallBackRepositoryId);
                }
            } catch (ServiceNotFoundException $exception) {
                $repository = new Definition(
                    EntityRepository::class,
                    [
                        new Reference($serviceId),
                        new Reference(EntityReaderInterface::class),
                        new Reference(VersionManager::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                    ]
                );
                $repository->setPublic(true);

                $container->setDefinition($repositoryId, $repository);

                if ($fallBackRepositoryId) {
                    $container->setAlias($fallBackRepositoryId, new Alias($repositoryId, true));
                }
            }
            $repositoryNameMap[$entity] = $repositoryId;

            if ($fallBackRepositoryId) {
                $repositoryNameMap[$fallBackEntity] = $fallBackRepositoryId;
            }
        }

        $definitionRegistry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $definitionRegistry->replaceArgument(1, $entityNameMap);
        $definitionRegistry->replaceArgument(2, $repositoryNameMap);
    }

    private function makeFieldSerializersPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('shopware.field_serializer'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }

    private function makeFieldResolversPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('shopware.field_resolver'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }

    private function makeFieldAccessorBuildersPublic(ContainerBuilder $container): void
    {
        $servicesIds = array_keys($container->findTaggedServiceIds('shopware.field_accessor_builder'));

        foreach ($servicesIds as $servicesId) {
            $container->getDefinition($servicesId)->setPublic(true);
        }
    }
}
