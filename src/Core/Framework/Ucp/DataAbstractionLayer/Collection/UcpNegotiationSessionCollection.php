<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpNegotiationSessionEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @extends EntityCollection<UcpNegotiationSessionEntity>
 *
 * @internal
 */
#[Package('framework')]
class UcpNegotiationSessionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ucp_negotiation_session_collection';
    }

    protected function getExpectedClass(): string
    {
        return UcpNegotiationSessionEntity::class;
    }
}
