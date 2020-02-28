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

    public function getSalesChannelRepository(string $entityName): SalesChannelRepositoryInterface
    {
        /** @var SalesChannelRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->container->get($this->repositoryMap[$entityName]);

        return $salesChannelRepository;
    }

    public function get(string $class): EntityDefinition
    {
        if (strpos($class, $this->prefix) !== 0) {
            $class = $this->prefix . $class;
        }

        return parent::get($class);
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

    public function register(EntityDefinition $definition, ?string $serviceId = null): void
    {
        if (!$serviceId) {
            $serviceId = $this->prefix . get_class($definition);
        }

        parent::register($definition, $serviceId);
    }
}
