<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SalesChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @var string
     */
    private $prefix;

    public function __construct(
        string $prefix,
        ContainerInterface $container,
        array $definitionMap,
        array $repositoryMap
    ) {
        parent::__construct($container, $definitionMap, $repositoryMap);

        $this->prefix = $prefix;
    }

    public function getSalesChannelRepository(string $entityName): SalesChannelRepository
    {
        /** @var SalesChannelRepository $salesChannelRepository */
        $salesChannelRepository = $this->container->get($this->repositoryMap[$entityName]);

        return $salesChannelRepository;
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
        return array_filter($this->getDefinitions(), static function ($definition): bool {
            return $definition instanceof SalesChannelDefinitionInterface;
        });
    }
}
