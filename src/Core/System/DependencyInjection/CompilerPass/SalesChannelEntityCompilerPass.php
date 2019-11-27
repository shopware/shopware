<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

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
            $container->findTaggedServiceIds('shopware.sales_channel.entity.definition')
        );
        $baseDefinitions = $this->formatData($container->findTaggedServiceIds('shopware.entity.definition'));

        $sortedData = $this->sortData($salesChannelDefinitions, $baseDefinitions);

        foreach ($sortedData as $entityName => $definitions) {
            // if extended -> set up
            if (isset($definitions['extended'])) {
                $serviceId = $definitions['extended'];
                $entityNameMap[$entityName] = $serviceId;

                $this->setUpEntityDefinitionService($container, $serviceId);
                $container->setAlias(self::PREFIX . $serviceId, new Alias($serviceId, true));
            }

            // if both mask base with extended extended as base
            if (isset($definitions['extended'], $definitions['base'])) {
                $container->setAlias(self::PREFIX . $definitions['base'], new Alias($definitions['extended'], true));
            }

            // if base only clone definition
            if (\count($definitions) === 1 && isset($definitions['base'])) {
                $service = $container->getDefinition($definitions['base']);

                $clone = clone $service;
                $clone->removeMethodCall('compile');
                $clone->clearTags();
                $container->setDefinition(self::PREFIX . $definitions['base'], $clone);
                $this->setUpEntityDefinitionService($container, self::PREFIX . $definitions['base']);

                $entityNameMap[$entityName] = $definitions['base'];
            }
        }

        /** @var string $serviceId */
        foreach ($salesChannelDefinitions as $entityName => $serviceId) {
            $service = $container->getDefinition($serviceId);

            $repositoryId = 'sales_channel.' . $entityName . '.repository';

            try {
                $container->getDefinition($repositoryId);
            } catch (ServiceNotFoundException $exception) {
                $repository = new Definition(
                    SalesChannelRepository::class,
                    [
                        new Reference($service->getClass()),
                        new Reference(EntityReaderInterface::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                    ]
                );
                $repository->setPublic(true);

                $container->setDefinition($repositoryId, $repository);
            }

            $repositoryNameMap[$entityName] = $repositoryId;
        }

        $definitionRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->replaceArgument(0, self::PREFIX);
        $definitionRegistry->replaceArgument(2, $entityNameMap);
        $definitionRegistry->replaceArgument(3, $repositoryNameMap);
    }

    private function formatData(array $taggedServiceIds): array
    {
        $result = [];

        foreach ($taggedServiceIds as $serviceId => $tags) {
            if (!isset($tags[0]['entity'])) {
                throw new \RuntimeException(sprintf('Missing attribute "entity" on tag "shopware.entity.definition" for definition with id "%s"', $serviceId));
            }

            $entityName = $tags[0]['entity'];
            $result[$entityName] = $serviceId;
        }

        return $result;
    }

    private function sortData(array $salesChannelDefinitions, array $baseDefinitions): array
    {
        $sorted = [];

        foreach ($baseDefinitions as $entityName => $serviceId) {
            $sorted[$entityName] = [
                'base' => $serviceId,
            ];
        }

        foreach ($salesChannelDefinitions as $entityName => $serviceId) {
            $sorted[$entityName]['extended'] = $serviceId;
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
}
