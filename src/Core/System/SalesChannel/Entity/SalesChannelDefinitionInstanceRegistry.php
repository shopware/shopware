<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Package('buyers-experience')]
class SalesChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $prefix,
        ContainerInterface $container,
        array $definitionMap,
        array $repositoryMap
    ) {
        parent::__construct($container, $definitionMap, $repositoryMap);
    }

    /**
     * @throws SalesChannelRepositoryNotFoundException
     */
    public function getSalesChannelRepository(string $entityName): SalesChannelRepository
    {
        $salesChannelRepositoryClass = $this->getSalesChannelRepositoryClassByEntityName($entityName);

        $salesChannelRepository = $this->container->get($salesChannelRepositoryClass);
        \assert($salesChannelRepository instanceof SalesChannelRepository);

        return $salesChannelRepository;
    }

    public function get(string $class): EntityDefinition
    {
        if (!str_starts_with($class, $this->prefix)) {
            $class = $this->prefix . $class;
        }

        return parent::get($class);
    }

    /**
     * @return array<SalesChannelDefinitionInterface>
     */
    public function getSalesChannelDefinitions(): array
    {
        return array_filter($this->getDefinitions(), static fn ($definition): bool => $definition instanceof SalesChannelDefinitionInterface);
    }

    public function register(EntityDefinition $definition, ?string $serviceId = null): void
    {
        if (!$serviceId) {
            $serviceId = $this->prefix . $definition::class;
        }

        parent::register($definition, $serviceId);
    }

    /**
     * @throws SalesChannelRepositoryNotFoundException
     */
    private function getSalesChannelRepositoryClassByEntityName(string $entityMame): string
    {
        if (!isset($this->repositoryMap[$entityMame])) {
            throw new SalesChannelRepositoryNotFoundException($entityMame);
        }

        return $this->repositoryMap[$entityMame];
    }
}
