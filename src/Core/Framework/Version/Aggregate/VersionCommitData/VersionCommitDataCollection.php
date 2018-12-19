<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class VersionCommitDataCollection extends EntityCollection
{
    public function filterByEntity(string $definition): self
    {
        return $this->filter(function (VersionCommitDataEntity $change) use ($definition) {
            /* @var string|EntityDefinition $definition */
            return $change->getEntityName() === $definition::getEntityName();
        });
    }

    public function filterByEntityPrimary(string $definition, array $primary): self
    {
        return $this->filter(function (VersionCommitDataEntity $change) use ($definition, $primary) {
            /** @var string|EntityDefinition $definition */
            if ($change->getEntityName() !== $definition::getEntityName()) {
                return false;
            }
            $diff = array_intersect($primary, $change->getEntityId());

            return $diff === $primary;
        });
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataEntity::class;
    }
}
