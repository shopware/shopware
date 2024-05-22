<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[Package('core')]
class AttributeEntityCompilerPass implements CompilerPassInterface
{
    public function __construct(private readonly AttributeEntityCompiler $compiler)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.entity');

        foreach ($services as $class => $_) {
            $definitions = $this->compiler->compile($class);

            foreach ($definitions as $definition) {
                if ($definition['type'] === 'entity') {
                    $this->definition($definition, $container, $definition['entity_name']);

                    $this->repository($container, $definition['entity_name']);

                    $this->translation($definition, $container, $definition['entity_name']);

                    continue;
                }

                if ($definition['type'] === 'mapping') {
                    $this->mapping($definition, $container);
                }
            }
        }
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

    /**
     * @param array<string, mixed> $meta
     */
    public function definition(array $meta, ContainerBuilder $container, string $entity): void
    {
        $definition = new Definition(AttributeEntityDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($entity . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($entity . '.definition'), $entity . '.definition']);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function translation(array $meta, ContainerBuilder $container, string $entity): void
    {
        if (!$this->hasTranslation($meta)) {
            return;
        }

        $definition = new Definition(AttributeTranslationDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($entity . '_translation.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($entity . '_translation.definition'), $entity . '_translation.definition']);

        $this->repository($container, $entity . '_translation');
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function hasTranslation(array $meta): bool
    {
        /** @var array<string, mixed> $field */
        foreach ($meta['fields'] as $field) {
            if (isset($field['translated']) && $field['translated']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function mapping(array $meta, ContainerBuilder $container): void
    {
        $definition = new Definition(AttributeMappingDefinition::class);
        $definition->addArgument($meta);
        $definition->setPublic(true);
        $container->setDefinition($meta['entity_name'] . '.definition', $definition);

        $registry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $registry->addMethodCall('register', [new Reference($meta['entity_name'] . '.definition'), $meta['entity_name'] . '.definition']);

        $this->repository($container, $meta['entity_name']);
    }
}
