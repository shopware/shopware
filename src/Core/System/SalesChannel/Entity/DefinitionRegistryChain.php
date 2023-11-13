<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class DefinitionRegistryChain
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $core,
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannel
    ) {
    }

    public function get(string $class): EntityDefinition
    {
        if ($this->salesChannel->has($class)) {
            return $this->salesChannel->get($class);
        }

        return $this->core->get($class);
    }

    public function getRepository(string $entity): EntityRepository
    {
        try {
            return $this->salesChannel->getRepository($entity);
        } catch (EntityRepositoryNotFoundException) {
            return $this->core->getRepository($entity);
        }
    }

    public function getByEntityName(string $type): EntityDefinition
    {
        try {
            return $this->salesChannel->getByEntityName($type);
        } catch (DefinitionNotFoundException) {
            return $this->core->getByEntityName($type);
        }
    }

    public function has(string $type): bool
    {
        return $this->salesChannel->has($type) || $this->core->has($type);
    }
}
