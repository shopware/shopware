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
    private array $addedDefinitions = [];

    /**
     * @var list<class-string<EntityDefinition>>
     */
    private array $addedSalesChannelDefinitions = [];

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
            if (static::getContainer()->has($definitionClass)) {
                /** @var EntityDefinition $definition */
                $definition = static::getContainer()->get($definitionClass);
            } else {
                $this->addedDefinitions[] = $definitionClass;
                $definition = new $definitionClass();

                $repoId = $definition->getEntityName() . '.repository';
                if (!static::getContainer()->has($repoId)) {
                    $repository = new EntityRepository(
                        $definition,
                        static::getContainer()->get(EntityReaderInterface::class),
                        static::getContainer()->get(VersionManager::class),
                        static::getContainer()->get(EntitySearcherInterface::class),
                        static::getContainer()->get(EntityAggregatorInterface::class),
                        static::getContainer()->get('event_dispatcher'),
                        static::getContainer()->get(EntityLoadedEventFactory::class)
                    );

                    static::getContainer()->set($repoId, $repository);
                }
            }

            static::getContainer()->get(DefinitionInstanceRegistry::class)->register($definition);

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

        if (static::getContainer()->has($serviceId)) {
            /** @var EntityDefinition $definition */
            $definition = static::getContainer()->get($serviceId);

            static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->register($definition);

            return $definition;
        }

        $salesChannelDefinition = new $definitionClass();
        $this->addedSalesChannelDefinitions[] = $definitionClass;
        static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->register($salesChannelDefinition);

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

            if (static::getContainer()->has($extensionsClass)) {
                /** @var EntityExtension $extension */
                $extension = static::getContainer()->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                static::getContainer()->set($extensionsClass, $extension);
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
        $definition = static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->get($definitionClass);
        foreach ($extensionsClasses as $extensionsClass) {
            $this->addedExtensions[] = $extensionsClass;

            if (static::getContainer()->has($extensionsClass)) {
                /** @var EntityExtension $extension */
                $extension = static::getContainer()->get($extensionsClass);
            } else {
                $extension = new $extensionsClass();
                static::getContainer()->set($extensionsClass, $extension);
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
            if (static::getContainer()->has($extension->getDefinitionClass())) {
                /** @var EntityDefinition $definition */
                $definition = static::getContainer()->get($extension->getDefinitionClass());

                $definition->removeExtension($extension);

                $salesChannelDefinitionId = 'sales_channel_definition.' . $extension->getDefinitionClass();

                if (static::getContainer()->has($salesChannelDefinitionId)) {
                    /** @var EntityDefinition $definition */
                    $definition = static::getContainer()->get('sales_channel_definition.' . $extension->getDefinitionClass());

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

            $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
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

            $registry = static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
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
