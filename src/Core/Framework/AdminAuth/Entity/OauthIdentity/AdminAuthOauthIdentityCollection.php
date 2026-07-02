<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @extends EntityCollection<AdminAuthOauthIdentityEntity>
 */
#[Package('framework')]
class AdminAuthOauthIdentityCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_auth_oauth_identity_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminAuthOauthIdentityEntity::class;
    }
}
