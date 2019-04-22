<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class SalesChannelEntityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->collectDefinitions($container);
    }

    private function collectDefinitions(ContainerBuilder $container): void
    {
        $classes = [];
        $services = array_keys($container->findTaggedServiceIds('shopware.sales_channel.entity.definition'));

        /** @var string|EntityDefinition $serviceId */
        foreach ($services as $serviceId) {
            $service = $container->getDefinition($serviceId);
            $entity = $serviceId::getEntityName();

            $repositoryId = 'sales_channel.' . $entity . '.repository';
            try {
                $container->getDefinition($repositoryId);
            } catch (ServiceNotFoundException $exception) {
                $repository = new Definition(
                    SalesChannelRepository::class,
                    [
                        $service->getClass(),
                        new Reference(EntityReaderInterface::class),
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

        $registry = $container->getDefinition(SalesChannelDefinitionRegistry::class);
        $registry->replaceArgument(0, $classes);
    }
}
