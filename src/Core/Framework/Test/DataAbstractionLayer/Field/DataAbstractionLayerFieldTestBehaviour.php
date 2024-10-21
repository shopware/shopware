<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MappingEntityClassesException;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait DataAbstractionLayerFieldTestBehaviour
{
    /**
     * @var list<class-string<EntityDefinition>>
     */
    private $addedDefinitions = [];

    /**
     * @var list<class-string<EntityDefinition>>
     */
    private $addedSalesChannelDefinitions = [];

    /**
     * @var list<class-string<EntityExtension>>
     */
    private array $addedExtensions = [];

    protected function tearDown(): void
    {
        $this->removeExtension(...$this->addedExtensions);
        $this->removeDefinitions(...$this->addedDefinitions);
        $this->removeSalesChannelDefinitions(...$this->addedSalesChannelDefinitions);

        $this->addedDefinitions = [];
        $this->addedSalesChannelDefinitions = [];
        $this->addedExtensions = [];
    }

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
                $this->addedDefinitions[] = $definitionClass;
                $definition = new $definitionClass();

                $repoId = $definition->getEntityName() . '.repository';
                if (!$this->getContainer()->has($repoId)) {
                    $repository = new EntityRepository(
                        $definition,
                        $this->getContainer()->get(EntityReaderInterface::class),
                        $this->getContainer()->get(VersionManager::class),
                        $this->getContainer()->get(EntitySearcherInterface::class),
                        $this->getContainer()->get(EntityAggregatorInterface::class),
                        $this->getContainer()->get('event_dispatcher'),
                        $this->getContainer()->get(EntityLoadedEventFactory::class)
                    );

                    $this->getContainer()->set($repoId, $repository);
                }
            }

            $this->getContainer()->get(DefinitionInstanceRegistry::class)->register($definition);

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

            $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->register($definition);

            return $definition;
        }

        $salesChannelDefinition = new $definitionClass();
        $this->addedSalesChannelDefinitions[] = $definitionClass;
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
            $this->addedExtensions[] = $extensionsClass;

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
            $this->addedExtensions[] = $extensionsClass;

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
     * @param class-string<EntityExtension> ...$extensionsClasses
     */
    private function removeExtension(string ...$extensionsClasses): void
    {
        foreach ($extensionsClasses as $extensionsClass) {
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

    /**
     * @param class-string<EntityDefinition> ...$definitionClasses
     */
    private function removeDefinitions(string ...$definitionClasses): void
    {
        foreach ($definitionClasses as $definitionClass) {
            $definition = new $definitionClass();

            $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
            \Closure::bind(function () use ($definition): void {
                unset(
                    $this->definitions[$definition->getEntityName()],
                    $this->repositoryMap[$definition->getEntityName()],
                );

                try {
                    unset($this->entityClassMapping[$definition->getEntityClass()]);
                } catch (MappingEntityClassesException) {
                }
            }, $registry, $registry)();
        }
    }

    /**
     * @param class-string<EntityDefinition> ...$definitionClasses
     */
    private function removeSalesChannelDefinitions(string ...$definitionClasses): void
    {
        foreach ($definitionClasses as $definitionClass) {
            $definition = new $definitionClass();

            $registry = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
            \Closure::bind(function () use ($definition): void {
                unset(
                    $this->definitions[$definition->getEntityName()],
                    $this->repositoryMap[$definition->getEntityName()],
                    $this->entityClassMapping[$definition->getEntityClass()],
                );
            }, $registry, $registry)();
        }
    }
}
