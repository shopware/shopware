<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\AbstractFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DefinitionInstanceRegistry
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $repositoryMap;

    /**
     * @var array
     */
    protected $definitions;

    /**
     * @var array
     */
    protected $entityClassMapping;

    /**
     * @internal
     *
     * @param array $definitionMap array of $entityName => $definitionServiceId,
     *                             eg. 'product' => '\Shopware\Core\Content\Product\ProductDefinition'
     * @param array $repositoryMap array of $entityName => $repositoryServiceId, eg. 'product' => 'product.repository'
     */
    public function __construct(ContainerInterface $container, array $definitionMap, array $repositoryMap)
    {
        $this->container = $container;
        $this->definitions = $definitionMap;
        $this->repositoryMap = $repositoryMap;
    }

    /**
     * @throws EntityRepositoryNotFoundException
     */
    public function getRepository(string $entityName): EntityRepositoryInterface
    {
        $entityRepositoryClass = $this->getEntityRepositoryClassByEntityName($entityName);

        /** @var EntityRepositoryInterface $entityRepository */
        $entityRepository = $this->container->get($entityRepositoryClass);

        return $entityRepository;
    }

    public function get(string $class): EntityDefinition
    {
        if ($this->container->has($class)) {
            $definition = $this->container->get($class);

            /** @var EntityDefinition $definition */
            return $definition;
        }

        throw new DefinitionNotFoundException($class);
    }

    /**
     * Shorthand to get the definition instance by class and use provided key as entity name as fallback
     */
    public function getByClassOrEntityName(string $key): EntityDefinition
    {
        try {
            return $this->get($key);
        } catch (DefinitionNotFoundException $e) {
            return $this->getByEntityName($key);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * @throws DefinitionNotFoundException
     */
    public function getByEntityName(string $entityName): EntityDefinition
    {
        $definitionClass = $this->getDefinitionClassByEntityName($entityName);

        if ($this->container->has($definitionClass)) {
            return $this->get($definitionClass);
        }

        throw new DefinitionNotFoundException($entityName);
    }

    /**
     * @return EntityDefinition[]
     */
    public function getDefinitions(): array
    {
        return array_map(function (string $name): EntityDefinition {
            return $this->get($name);
        }, $this->definitions);
    }

    public function getSerializer(string $serializerClass): FieldSerializerInterface
    {
        /** @var FieldSerializerInterface $fieldSerializer */
        $fieldSerializer = $this->container->get($serializerClass);

        return $fieldSerializer;
    }

    /**
     * @return AbstractFieldResolver
     */
    public function getResolver(string $resolverClass)
    {
        /** @var AbstractFieldResolver $fieldResolver */
        $fieldResolver = $this->container->get($resolverClass);

        return $fieldResolver;
    }

    public function getAccessorBuilder(string $accessorBuilderClass): FieldAccessorBuilderInterface
    {
        /** @var FieldAccessorBuilderInterface $fieldAccessorBuilder */
        $fieldAccessorBuilder = $this->container->get($accessorBuilderClass);

        return $fieldAccessorBuilder;
    }

    public function getByEntityClass(Entity $entity): ?EntityDefinition
    {
        $map = $this->loadClassMapping();

        $source = \get_class($entity);

        return $map[$source] ?? null;
    }

    public function register(EntityDefinition $definition, ?string $serviceId = null): void
    {
        if (!$serviceId) {
            $serviceId = \get_class($definition);
        }

        if (!$this->container->has($serviceId)) {
            $this->container->set($serviceId, $definition);
        }

        if ($this->entityClassMapping !== null) {
            $this->entityClassMapping[$definition->getEntityClass()] = $definition;
        }

        $this->definitions[$definition->getEntityName()] = $serviceId;

        $this->repositoryMap[$definition->getEntityName()] = $definition->getEntityName() . '.repository';

        $definition->compile($this);
    }

    private function loadClassMapping(): array
    {
        if ($this->entityClassMapping !== null) {
            return $this->entityClassMapping;
        }

        $this->entityClassMapping = [];

        foreach ($this->definitions as $element) {
            $definition = $this->container->get($element);

            if (!$definition) {
                continue;
            }

            try {
                $class = $definition->getEntityClass();

                $this->entityClassMapping[$class] = $definition;
            } catch (\Throwable $e) {
            }
        }

        return $this->entityClassMapping;
    }

    /**
     * @throws DefinitionNotFoundException
     */
    private function getDefinitionClassByEntityName(string $entityName): string
    {
        if (!isset($this->definitions[$entityName])) {
            throw new DefinitionNotFoundException($entityName);
        }

        return $this->definitions[$entityName];
    }

    /**
     * @throws EntityRepositoryNotFoundException
     */
    private function getEntityRepositoryClassByEntityName(string $entityName): string
    {
        if (!isset($this->repositoryMap[$entityName])) {
            throw new EntityRepositoryNotFoundException($entityName);
        }

        return $this->repositoryMap[$entityName];
    }
}
