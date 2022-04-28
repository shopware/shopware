<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Store\Struct\PermissionStruct;

/**
 * @internal only for use by the app-system
 */
class PermissionsDeltaProvider extends AbstractAppDeltaProvider
{
    public const DELTA_NAME = 'permissions';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    public function getDelta(Manifest $manifest, AppEntity $app): array
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return Permissions::fromArray([])->asParsedPrivileges();
        }

        $aclRole = $app->getAclRole();

        if (!$aclRole) {
            return $this->makeCategorizedPermissions($permissions->asParsedPrivileges());
        }

        $newPrivileges = $permissions->asParsedPrivileges();
        $currentPrivileges = $aclRole->getPrivileges();

        $privilegesDelta = array_diff($newPrivileges, $currentPrivileges);

        return $this->makeCategorizedPermissions($privilegesDelta);
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $delta = $this->getDelta($manifest, $app);

        return \count($delta) > 0;
    }

    private function makePermissions(array $appPrivileges): array
    {
        $permissions = [];

        foreach ($appPrivileges as $privilege) {
            if ($this->isCrudPrivilege($privilege)) {
                $entityAndOperation = explode(':', $privilege);
                if (\array_key_exists($entityAndOperation[1], AclRoleDefinition::PRIVILEGE_DEPENDENCE)) {
                    $permissions[] = array_combine(['entity', 'operation'], $entityAndOperation);

                    continue;
                }
            }

            $permissions[] = ['entity' => 'additional_privileges', 'operation' => $privilege];
        }

        return $permissions;
    }

    private function isCrudPrivilege(string $privilege): bool
    {
        return substr_count($privilege, ':') === 1;
    }

    private function makeCategorizedPermissions(array $privilegesDelta): array
    {
        $permissions = $this->makePermissions($privilegesDelta);

        $permissionCollection = new PermissionCollection();

        foreach ($permissions as $permission) {
            $permissionCollection->add(PermissionStruct::fromArray([
                'entity' => $permission['entity'],
                'operation' => $permission['operation'],
            ]));
        }

        return $permissionCollection->getCategorizedPermissions();
    }
}
