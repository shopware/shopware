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
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class EntityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $classes = [];
        $services = array_keys($container->findTaggedServiceIds('shopware.entity.definition'));

        /** @var string|EntityDefinition $serviceId */
        foreach ($services as $serviceId) {
            $service = $container->getDefinition($serviceId);
            $entity = $serviceId::getEntityName();

            $repositoryId = $entity . '.repository';
            try {
                $container->getDefinition($repositoryId);
            } catch (ServiceNotFoundException $exception) {
                $repository = new Definition(
                    EntityRepository::class,
                    [
                        $service->getClass(),
                        new Reference(EntityReaderInterface::class),
                        new Reference(VersionManager::class),
                        new Reference(EntitySearcherInterface::class),
                        new Reference(EntityAggregatorInterface::class),
                        new Reference('event_dispatcher'),
                    ]
                );
                $repository->setPublic(true);

                $container->setDefinition($repositoryId, $repository);
            }

            $classes[$serviceId] = $repositoryId;
        }

        $registry = $container->getDefinition(DefinitionRegistry::class);
        $registry->replaceArgument(0, $classes);
    }
}
