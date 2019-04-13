<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Symfony\Component\DependencyInjection\Container;

class SalesChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @var array
     */
    private $salesChannelDefinitionMap;

    private $prefix;

    public function __construct(string $prefix, array $salesChannelDefinitionMap, Container $container, array $definitionMap, array $repositoryMap)
    {
        parent::__construct($container, $definitionMap, $repositoryMap);
        $this->salesChannelDefinitionMap = $salesChannelDefinitionMap;
        $this->prefix = $prefix;
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
