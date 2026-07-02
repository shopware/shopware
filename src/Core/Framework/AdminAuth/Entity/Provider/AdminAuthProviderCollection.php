<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\Provider;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @extends EntityCollection<AdminAuthProviderEntity>
 */
#[Package('framework')]
class AdminAuthProviderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_auth_provider_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminAuthProviderEntity::class;
    }
}
