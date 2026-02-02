<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\MappingMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributeEntityCompilerPass implements CompilerPassInterface
{
    public function __construct(private readonly AttributeEntityCompiler $compiler)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.entity');

        foreach ($services as $class => $_) {
            /** @var class-string<Entity> $class */
            $compiled = $this->compiler->compile($class);

            if ($compiled->isEmpty()) {
                continue;
            }

            if ($compiled->entity !== null) {
                $this->definition($compiled->entity, $container);
                $this->repository($container, $compiled->entity->entityName);

                if ($compiled->entity->hasTranslation()) {
                    $this->translation($compiled->entity, $container);
                }
            }

            foreach ($compiled->mappings as $mapping) {
                $this->mapping($mapping, $container);
            }
        }
    }

    public function definition(EntityMetadata $meta, ContainerBuilder $container): void
    {
        $this->registerDefinition($container, AttributeEntityDefinition::class, $meta, $meta->entityName);
    }

    private function translation(EntityMetadata $meta, ContainerBuilder $container): void
    {
        $entityName = $meta->entityName . '_translation';
        $this->registerDefinition($container, AttributeTranslationDefinition::class, $meta, $entityName);
        $this->repository($container, $entityName);
    }

    private function mapping(MappingMetadata $meta, ContainerBuilder $container): void
    {
        $this->registerDefinition($container, AttributeMappingDefinition::class, $meta, $meta->entityName);
        $this->repository($container, $meta->entityName);
    }

    /**
     * @param class-string<AttributeEntityDefinition|AttributeTranslationDefinition|AttributeMappingDefinition> $definitionClass
     */
    private function registerDefinition(
        ContainerBuilder $container,
        string $definitionClass,
        EntityMetadata|MappingMetadata $meta,
        string $entityName
    ): void {
        $serviceId = $entityName . '.definition';

        $definition = new Definition($definitionClass);
        $definition->addArgument($meta->toDefinition());
        $definition->setPublic(true);
        $definition->addTag('shopware.entity.definition');
        $container->setDefinition($serviceId, $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $salesChannelRegistry = $container->getDefinition(SalesChannelDefinitionInstanceRegistry::class);

        $registry->addMethodCall('register', [new Reference($serviceId), $serviceId]);
        $salesChannelRegistry->addMethodCall('register', [new Reference($serviceId), 'sales_channel_definition.' . $serviceId]);
    }

    private function repository(ContainerBuilder $container, string $entity): void
    {
        $repository = new Definition(
            EntityRepository::class,
            [
                new Reference($entity . '.definition'),
                new Reference(EntityReaderInterface::class),
                new Reference(VersionManager::class),
                new Reference(EntitySearcherInterface::class),
                new Reference(EntityAggregatorInterface::class),
                new Reference('event_dispatcher'),
                new Reference(EntityLoadedEventFactory::class),
            ]
        );
        $repository->setPublic(true);

        $container->setDefinition($entity . '.repository', $repository);
    }
}
