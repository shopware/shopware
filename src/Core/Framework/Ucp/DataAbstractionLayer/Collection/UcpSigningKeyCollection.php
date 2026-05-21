<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @extends EntityCollection<UcpSigningKeyEntity>
 *
 * @internal
 */
#[Package('framework')]
class UcpSigningKeyCollection extends EntityCollection
{
    /**
     * @return list<UcpSigningKeyEntity>
     */
    public function filterByStatus(string $status): array
    {
        return array_values(array_filter(
            $this->getElements(),
            static fn (UcpSigningKeyEntity $e): bool => $e->getStatus() === $status
        ));
    }

    public function getActive(): ?UcpSigningKeyEntity
    {
        foreach ($this->getElements() as $entity) {
            if ($entity->getStatus() === UcpSigningKeyEntity::STATUS_ACTIVE) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @return list<UcpSigningKeyEntity>
     */
    public function getPublishable(): array
    {
        return array_values(array_filter(
            $this->getElements(),
            static fn (UcpSigningKeyEntity $e): bool => \in_array(
                $e->getStatus(),
                [UcpSigningKeyEntity::STATUS_ACTIVE, UcpSigningKeyEntity::STATUS_RETIRING],
                true
            )
        ));
    }

    public function getApiAlias(): string
    {
        return 'ucp_signing_key_collection';
    }

    protected function getExpectedClass(): string
    {
        return UcpSigningKeyEntity::class;
    }
}
