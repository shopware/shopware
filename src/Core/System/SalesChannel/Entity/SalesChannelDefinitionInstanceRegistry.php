<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SalesChannelDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(
        string $prefix,
        ContainerInterface $container,
        array $definitionMap,
        array $repositoryMap
    ) {
        parent::__construct($container, $definitionMap, $repositoryMap);

        $this->prefix = $prefix;
    }

    /**
     * @throws SalesChannelRepositoryNotFoundException
     */
    public function getSalesChannelRepository(string $entityName): SalesChannelRepositoryInterface
    {
        $salesChannelRepositoryClass = $this->getSalesChannelRepositoryClassByEntityName($entityName);

        /** @var SalesChannelRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->container->get($salesChannelRepositoryClass);

        return $salesChannelRepository;
    }

    public function get(string $class): EntityDefinition
    {
        if (mb_strpos($class, $this->prefix) !== 0) {
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
            $serviceId = $this->prefix . \get_class($definition);
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
