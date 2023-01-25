<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<VersionCommitDataEntity>
 */
#[Package('core')]
class VersionCommitDataCollection extends EntityCollection
{
    public function filterByEntity(EntityDefinition $definition): self
    {
        return $this->filter(fn (VersionCommitDataEntity $change) => $change->getEntityName() === $definition->getEntityName());
    }

    public function filterByEntityPrimary(EntityDefinition $definition, array $primary): self
    {
        return $this->filter(function (VersionCommitDataEntity $change) use ($definition, $primary) {
            if ($change->getEntityName() !== $definition->getEntityName()) {
                return false;
            }
            $diff = array_intersect($primary, $change->getEntityId());

            return $diff === $primary;
        });
    }

    public function getApiAlias(): string
    {
        return 'dal_version_commit_data_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataEntity::class;
    }
}
