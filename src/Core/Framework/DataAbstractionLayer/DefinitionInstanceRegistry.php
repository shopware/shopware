<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

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

    public function getRepository(string $entityName): EntityRepositoryInterface
    {
        /** @var EntityRepositoryInterface $entityRepository */
        $entityRepository = $this->container->get($this->repositoryMap[$entityName]);

        return $entityRepository;
    }

    public function get(string $class): EntityDefinition
    {
        /** @var EntityDefinition $entityDefinition */
        $entityDefinition = $this->container->get($class);

        return $entityDefinition;
    }

    /**
     * @throws DefinitionNotFoundException
     */
    public function getByEntityName(string $name): EntityDefinition
    {
        try {
            return $this->get($this->definitions[$name]);
        } catch (ServiceNotFoundException $e) {
            throw new DefinitionNotFoundException($name);
        }
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

    public function getResolver(string $resolverClass): FieldResolverInterface
    {
        /** @var FieldResolverInterface $fieldResolver */
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

        $source = get_class($entity);

        return $map[$source] ?? null;
    }

    public function register(EntityDefinition $definition): void
    {
        $class = get_class($definition);
        if (!$this->container->has($class)) {
            $this->container->set($class, $definition);
        }

        if ($this->entityClassMapping !== null) {
            $this->entityClassMapping[$definition->getEntityClass()] = $definition;
        }

        $this->definitions[$definition->getEntityName()] = $class;

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
                continue;
            }
        }

        return $this->entityClassMapping;
    }
}
