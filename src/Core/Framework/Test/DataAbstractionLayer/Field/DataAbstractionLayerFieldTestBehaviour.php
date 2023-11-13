<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait DataAbstractionLayerFieldTestBehaviour
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * @param class-string<EntityDefinition> ...$definitionClasses
     */
    protected function registerDefinition(string ...$definitionClasses): EntityDefinition
    {
        $ret = null;

        foreach ($definitionClasses as $definitionClass) {
            if ($this->getContainer()->has($definitionClass)) {
                /** @var EntityDefinition $definition */
                $definition = $this->getContainer()->get($definitionClass);
            } else {
                $definition = new $definitionClass();
                $this->getContainer()->get(DefinitionInstanceRegistry::class)->register($definition);

                if (!$this->getContainer()->has($definition->getEntityName() . '.repository')) {
                    $repository = new EntityRepository(
                        $definition,
                        $this->getContainer()->get(EntityReaderInterface::class),
                        $this->getContainer()->get(VersionManager::class),
                        $this->getContainer()->get(EntitySearcherInterface::class),
                        $this->getContainer()->get(EntityAggregatorInterface::class),
                        $this->getContainer()->get('event_dispatcher'),
                        $this->getContainer()->get(EntityLoadedEventFactory::class)
                    );

                    $this->getContainer()->set($definition->getEntityName() . '.repository', $repository);
                }
            }

            if ($ret === null) {
                $ret = $definition;
            }
        }

        if (!$ret) {
            throw new \InvalidArgumentException('Need at least one definition class to register.');
        }

        return $ret;
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    protected function registerSalesChannelDefinition(string $definitionClass): EntityDefinition
    {
        $serviceId = 'sales_channel_definition.' . $definitionClass;

        if ($this->getContainer()->has($serviceId)) {
            /** @var EntityDefinition $definition */
            $definition = $this->getContainer()->get($serviceId);

            return $definition;
        }

        $salesChannelDefinition = new $definitionClass();
        $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->register($salesChannelDefinition);

        return $salesChannelDefinition;
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string<EntityExtension> ...$extensionsClasses
     */
    protected function registerDefinitionWithExtensions(string $definitionClass, string ...$extensionsClasses): EntityDefinition
    {
        $definition = $this->registerDefinition($definitionClass);
        foreach ($extensionsClasses as $extensionsClass) {
            if ($this->getContainer()->has($extensionsClass)) {
                /** @var EntityExtension $extension */
                $extension = $this->getContainer()->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                $this->getContainer()->set($extensionsClass, $extension);
            }

            $definition->addExtension($extension);
        }

        return $definition;
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param class-string<EntityExtension> ...$extensionsClasses
     */
    protected function registerSalesChannelDefinitionWithExtensions(string $definitionClass, string ...$extensionsClasses): EntityDefinition
    {
        $definition = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->get($definitionClass);
        foreach ($extensionsClasses as $extensionsClass) {
            if ($this->getContainer()->has($extensionsClass)) {
                /** @var EntityExtension $extension */
                $extension = $this->getContainer()->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                $this->getContainer()->set($extensionsClass, $extension);
            }

            $definition->addExtension($extension);
        }

        return $definition;
    }

    protected function removeExtension(string ...$extensionsClasses): void
    {
        foreach ($extensionsClasses as $extensionsClass) {
            /** @var EntityExtension $extension */
            $extension = new $extensionsClass();
            if ($this->getContainer()->has($extension->getDefinitionClass())) {
                /** @var EntityDefinition $definition */
                $definition = $this->getContainer()->get($extension->getDefinitionClass());

                $definition->removeExtension($extension);

                $salesChannelDefinitionId = 'sales_channel_definition.' . $extension->getDefinitionClass();

                if ($this->getContainer()->has($salesChannelDefinitionId)) {
                    /** @var EntityDefinition $definition */
                    $definition = $this->getContainer()->get('sales_channel_definition.' . $extension->getDefinitionClass());

                    $definition->removeExtension($extension);
                }
            }
        }
    }
}
