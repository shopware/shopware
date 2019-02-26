<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\RepositoryNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains all registered entity definitions.
 */
class DefinitionRegistry
{
    /**
     * @var string[]
     */
    private $definitions = [];

    /**
     * @var string[]
     */
    private $repositories = [];

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(array $elements, ContainerInterface $container)
    {
        /** @var EntityDefinition|string $definition */
        foreach ($elements as $definition => $repository) {
            $this->definitions[$definition::getEntityName()] = $definition;
            $this->repositories[$definition::getEntityName()] = $repository;
        }

        $this->container = $container;
    }

    /**
     * @throws DefinitionNotFoundException
     *
     * @return string|EntityDefinition
     */
    public function get(string $entityName): string
    {
        if (isset($this->definitions[$entityName])) {
            return $this->definitions[$entityName];
        }

        throw new DefinitionNotFoundException($entityName);
    }

    public function getRepository(string $entityName): EntityRepositoryInterface
    {
        if (isset($this->repositories[$entityName]) && $this->container->has($this->repositories[$entityName])) {
            /** @var EntityRepositoryInterface $repository */
            $repository = $this->container->get($this->repositories[$entityName]);

            return $repository;
        }

        throw new RepositoryNotFoundException($entityName);
    }

    /**
     * @return EntityDefinition[]|string[]
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }
}
