<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionsDeltaProvider extends AbstractAppDeltaProvider
{
    final public const DELTA_NAME = 'permissions';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    /**
     * @return array<string, PermissionCollection>
     */
    public function getReport(Manifest $manifest, AppEntity $app): array
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return [];
        }

        return Utils::makeCategorizedPermissions($permissions->asParsedPrivileges());
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return false;
        }

        $aclRole = $app->getAclRole();

        if (!$aclRole) {
            return true;
        }

        $newPrivileges = $permissions->asParsedPrivileges();
        $currentPrivileges = $aclRole->getPrivileges();

        $privilegesDelta = array_diff($newPrivileges, $currentPrivileges);

        return \count($privilegesDelta) > 0;
    }
}
