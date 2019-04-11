<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\Container;

class SalesChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @var array
     */
    private $salesChannelDefinitionMap;

    /**
     * @var string
     */
    private $prefix;
    /**
     * @var Container
     */
    private $container;
    /**
     * @var array
     */
    private $repositoryMap;

    public function __construct(
        string $prefix,
        array $salesChannelDefinitionMap,
        Container $container,
        array $definitionMap,
        array $repositoryMap
    ) {
        parent::__construct($container, $definitionMap, $repositoryMap);

        $this->salesChannelDefinitionMap = $salesChannelDefinitionMap;
        $this->prefix = $prefix;
        $this->container = $container;
        $this->repositoryMap = $repositoryMap;
    }

    public function getSalesChannelRepository(string $entityName): SalesChannelRepository
    {
        return $this->container->get($this->repositoryMap[$entityName]);
    }

    public function get(string $name): EntityDefinition
    {
        return parent::get($this->prefix . $name);
    }

    /**
     * @return SalesChannelDefinitionInterface[]
     */
    public function getSalesChannelDefinitions(): array
    {
        return array_filter($this->getDefinitions(), static function (EntityDefinition $definition): bool {
            return $definition instanceof SalesChannelDefinitionInterface;
        });
    }
}
