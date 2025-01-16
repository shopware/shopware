<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

#[Package('core')]
class SalesChannelEntityCompilerPass implements CompilerPassInterface
{
    private const PREFIX = 'sales_channel_definition.';

    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $entityNameMap = [];
        $repositoryNameMap = [];

        $salesChannelDefinitions = $this->formatData(
            $container->findTaggedServiceIds('shopware.sales_channel.entity.definition'),
            $container
        );

        $baseDefinitions = $this->formatData(
            $container->findTaggedServiceIds('shopware.entity.definition'),
            $container
        );

        $sortedData = $this->sortData($salesChannelDefinitions, $baseDefinitions);

        foreach ($sortedData as $entityName => $definitions) {
            // if extended -> set up
            if (isset($definitions['extended'])) {
                $serviceId = $definitions['extended'];
                $entityNameMap[$entityName] = $serviceId;

                if (isset($definitions['alias'])) {
                    $entityNameMap[$definitions['alias']] = $serviceId;
                }

                $this->setUpEntityDefinitionService($container, $serviceId);
                $container->setAlias(self::PREFIX . $serviceId, new Alias($serviceId, true));
            }

            // if both mask base with extended extended as base
            if (isset($definitions['extended'], $definitions['base'])) {
                $container->setAlias(self::PREFIX . $definitions['base'], new Alias($definitions['extended'], true));
            }

            // if base only clone definition
            if (!isset($definitions['extended']) && isset($definitions['base'])) {
                $service = $container->getDefinition($definitions['base']);

                $clone = clone $service;
                $clone->removeMethodCall('compile');
                $clone->clearTags();
                $container->setDefinition(self::PREFIX . $definitions['base'], $clone);
                $this->setUpEntityDefinitionService($container, self::PREFIX . $definitions['base']);

                $entityNameMap[$entityName] = $definitions['base'];

                if (isset($definitions['alias'])) {
                    $entityNameMap[$definitions['alias']] = $definitions['base'];
                }
            }
        }

        /** @var string $serviceId */
        foreach ($salesChannelDefinitions as $serviceId => $entityNames) {
            $service = $container->getDefinition($serviceId);

            $repositoryId = 'sales_channel.' . $entityNames['entityName'] . '.repository';

            try {
                $repository = $container->getDefinition($repositoryId);
                $repository->setPublic(true);
            } catch (ServiceNotFoundException) {
                $serviceClass = $service->getClass();
                \assert(\is_string($serviceClass));
                $repository = new Definition(
                    SalesChannelRepository::class,
                    [
                        new Reference($serviceClass),
                        new Reference(EntityReaderInterface::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                        new Reference(EntityLoadedEventFactory::class),
                    ]
                );
                $repository->setPublic(true);

                $container->setDefinition($repositoryId, $repository);

                if (isset($entityNames['fallBack'])) {
                    $container->setAlias('sales_channel.' . $entityNames['fallBack'] . '.repository', new Alias($repositoryId, true));
                }
            }

            $repositoryNameMap[$entityNames['entityName']] = $repositoryId;

            if (isset($entityNames['fallBack'])) {
                $repositoryNameMap[$entityNames['fallBack']] = $repositoryId;
            }
        }

        $definitionRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->replaceArgument(0, self::PREFIX);
        $definitionRegistry->replaceArgument(2, $entityNameMap);
        $definitionRegistry->replaceArgument(3, $repositoryNameMap);

        if (Feature::isActive('v6.7.0.0')) {
            $this->addExtensions($container, $baseDefinitions, $salesChannelDefinitions);
        }
    }

    /**
     * @param array<string, array<mixed>> $taggedServiceIds
     *
     * @return array<string, array{entityName: string, fallback?: string}>
     */
    private function formatData(
        array $taggedServiceIds,
        ContainerBuilder $container
    ): array {
        $result = [];

        foreach ($taggedServiceIds as $serviceId => $tags) {
            $service = $container->getDefinition($serviceId);

            /** @var string $class */
            $class = $service->getClass();
            /** @var EntityDefinition $instance */
            $instance = new $class();
            $entityName = $instance->getEntityName();
            $result[$serviceId]['entityName'] = $entityName;

            if (isset($tags[0]['entity'])) {
                $result[$serviceId]['fallBack'] = $tags[0]['entity'];
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, string>> $salesChannelDefinitions
     * @param array<string, array<string, string>> $baseDefinitions
     *
     * @return array<string, array<string, string>>
     */
    private function sortData(array $salesChannelDefinitions, array $baseDefinitions): array
    {
        $sorted = [];

        foreach ($baseDefinitions as $serviceId => $entityNames) {
            $sorted[$entityNames['entityName']]['base'] = $serviceId;

            if (isset($entityNames['fallBack'])) {
                $sorted[$entityNames['entityName']]['alias'] = $entityNames['fallBack'];
            }
        }

        foreach ($salesChannelDefinitions as $serviceId => $entityNames) {
            $sorted[$entityNames['entityName']]['extended'] = $serviceId;

            if (isset($entityNames['fallBack'])) {
                $sorted[$entityNames['entityName']]['alias'] = $entityNames['fallBack'];
            }
        }

        return $sorted;
    }

    private function setUpEntityDefinitionService(ContainerBuilder $container, string $serviceId): void
    {
        $service = $container->getDefinition($serviceId);
        $service->setPublic(true);
        $service->addMethodCall('compile', [
            new Reference(SalesChannelDefinitionInstanceRegistry::class),
        ]);
    }

    /**
     * @param array<string, array{entityName: string}> $baseEntityDefinitions
     * @param array<string, array{entityName: string}> $salesChannelDefinitions
     */
    private function addExtensions(ContainerBuilder $container, array $baseEntityDefinitions, array $salesChannelDefinitions): void
    {
        $entityNameMap = [];
        $salesChannelNameMap = [];

        foreach ($baseEntityDefinitions as $definition => $attrs) {
            $entityNameMap[$attrs['entityName']] = $definition;
        }

        foreach ($salesChannelDefinitions as $definition => $attrs) {
            $salesChannelNameMap[$attrs['entityName']] = $definition;
        }

        foreach ($container->findTaggedServiceIds('shopware.entity.extension') as $id => $tags) {
            $definition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $definition->getClass() ?? $id;

            /** @var EntityExtension $classObject */
            $classObject = (new \ReflectionClass($className))->newInstanceWithoutConstructor();

            if (!\array_key_exists($classObject->getEntityName(), $entityNameMap)) {
                throw DependencyInjectionException::definitionNotFound($classObject->getEntityName());
            }

            if (!$container->hasDefinition($entityNameMap[$classObject->getEntityName()])) {
                throw DependencyInjectionException::definitionNotFound($classObject->getEntityName());
            }

            $definition = $container->getDefinition($entityNameMap[$classObject->getEntityName()]);

            $definition->addMethodCall('addExtension', [new Reference($id)]);

            if (isset($salesChannelNameMap[$classObject->getEntityName()])) {
                $definition = $container->getDefinition($salesChannelNameMap[$classObject->getEntityName()]);
                $definition->addMethodCall('addExtension', [new Reference($id)]);
            }
        }

        foreach ($container->findTaggedServiceIds('shopware.bulk.entity.extension') as $id => $tags) {
            $definition = $container->getDefinition($id);

            /** @var class-string $className */
            $className = $definition->getClass() ?? $id;

            /** @var BulkEntityExtension $classObject */
            $classObject = (new \ReflectionClass($className))->newInstanceWithoutConstructor();

            $entities = array_keys(iterator_to_array($classObject->collect()));

            foreach ($entities as $entity) {
                if (!\array_key_exists($entity, $entityNameMap)) {
                    throw DependencyInjectionException::definitionNotFound($entity);
                }

                if (!$container->hasDefinition($entityNameMap[$entity])) {
                    throw DependencyInjectionException::definitionNotFound($entity);
                }

                $filteredExtension = new Definition(FilteredBulkEntityExtension::class);
                $filteredExtension->addArgument($entity);
                $filteredExtension->addArgument(new Reference($id));

                $definition = $container->getDefinition($entityNameMap[$entity]);

                $definition->addMethodCall('addExtension', [$filteredExtension]);

                if (isset($salesChannelNameMap[$entity])) {
                    $definition = $container->getDefinition($salesChannelNameMap[$entity]);
                    $definition->addMethodCall('addExtension', [$filteredExtension]);
                }
            }
        }
    }
}
