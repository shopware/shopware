<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class EntityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds('shopware.entity.definition');
        $classes = [];
        foreach ($services as $serviceId => $attributes) {
            $service = $container->getDefinition($serviceId);

            $entity = $this->getEntity($service->getClass());

            $classes[$entity] = $service->getClass();

            $repositoryId = $entity . '.repository';

            if ($container->has($repositoryId)) {
                continue;
            }

            $repository = new Definition(EntityRepository::class, [
                $service->getClass(),
                new Reference(EntityReaderInterface::class),
                new Reference(VersionManager::class),
                new Reference(EntitySearcherInterface::class),
                new Reference(EntityAggregatorInterface::class),
                new Reference('event_dispatcher'),
            ]);
            $repository->setPublic(true);

            $container->setDefinition($repositoryId, $repository);
        }

        $registry = $container->getDefinition(DefinitionRegistry::class);
        $registry->replaceArgument(0, $classes);
    }

    private function getEntity(string $class): string
    {
        /** @var string|EntityDefinition $instance */
        $instance = new $class();

        return $instance::getEntityName();
    }
}
