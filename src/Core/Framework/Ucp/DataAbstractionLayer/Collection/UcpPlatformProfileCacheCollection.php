<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpPlatformProfileCacheEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @extends EntityCollection<UcpPlatformProfileCacheEntity>
 *
 * @internal
 */
#[Package('framework')]
class UcpPlatformProfileCacheCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ucp_platform_profile_cache_collection';
    }

    protected function getExpectedClass(): string
    {
        return UcpPlatformProfileCacheEntity::class;
    }
}
