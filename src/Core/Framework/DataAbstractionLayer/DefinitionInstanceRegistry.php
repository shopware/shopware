<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\FieldAccessorBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class DefinitionInstanceRegistry
{
    /**
     * @var Container
     */
    private $container;
    /**
     * @var array
     */
    private $definitions;

    /**
     * @var array
     */
    private $repositoryMap;

    public function __construct(Container $container, array $definitionMap, array $repositoryMap)
    {
        $this->container = $container;
        $this->definitions = $definitionMap;
        $this->repositoryMap = $repositoryMap;
    }

    public function __debugInfo()
    {
        return [
            'repositoryMapCount' => count($this->repositoryMap),
            'definitionCount' => count($this->definitions),
        ];
    }

    public function getRepository(string $entityName): EntityRepositoryInterface
    {
        return $this->container->get($this->repositoryMap[$entityName]);
    }

    public function get(string $name): EntityDefinition
    {
        return $this->container->get($name);
    }

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
        return $this->container->get($serializerClass);
    }

    public function getResolver(string $resolverClass): FieldResolverInterface
    {
        return $this->container->get($resolverClass);
    }

    public function getAccessorBuilder(string $accessorBuilderClass): FieldAccessorBuilderInterface
    {
        return $this->container->get($accessorBuilderClass);
    }
}
