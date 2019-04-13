<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Symfony\Component\DependencyInjection\Container;

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
            //            'repositoryMap' => $this->repositoryMap,
            //            'definitions' => $this->definitions,
        ];
    }

    public function getRepository(string $entityName) //: EntityRepositoryInterface
    {
        return $this->container->get($this->repositoryMap[$entityName]);
    }

    public function get(string $name): EntityDefinition
    {
        return $this->container->get($name);
    }

    public function getByEntityName(string $name): EntityDefinition
    {
        // @todo@jp DefinitionNotFoundException
        return $this->get($this->definitions[$name]);
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
}
