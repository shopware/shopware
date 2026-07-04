<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Entity\UserMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @extends EntityCollection<AdminAuthUserMethodEntity>
 */
#[Package('framework')]
class AdminAuthUserMethodCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_auth_user_method_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminAuthUserMethodEntity::class;
    }
}
