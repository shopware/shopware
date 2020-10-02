<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\Uuid\Uuid;

class PermissionPersister
{
    private const PRIVILEGE_DEPENDENCE = [
        AclRoleDefinition::PRIVILEGE_READ => [],
        AclRoleDefinition::PRIVILEGE_CREATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_UPDATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_DELETE => [AclRoleDefinition::PRIVILEGE_READ],
    ];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(?Permissions $permissions, string $roleId): void
    {
        $privileges = $this->generatePrivileges($permissions ? $permissions->getPermissions() : []);

        $this->addPrivileges($privileges, $roleId);
    }

    /**
     * @internal only for use by the app-system
     */
    public function removeRole(string $roleId): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    private function addPrivileges(array $privileges, string $roleId): void
    {
        $this->connection->executeUpdate(
            'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
            [
                'privileges' => json_encode($privileges),
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    private function generatePrivileges(array $permissions): array
    {
        $grantedPrivileges = array_map(static function (array $privileges): array {
            $grantedPrivileges = [];

            foreach ($privileges as $privilege) {
                $grantedPrivileges[] = $privilege;
                $grantedPrivileges = array_merge($grantedPrivileges, self::PRIVILEGE_DEPENDENCE[$privilege]);
            }

            return array_unique($grantedPrivileges);
        }, $permissions);

        $privilegeValues = [];
        foreach ($grantedPrivileges as $resource => $privileges) {
            $newPrivileges = array_map(static function (string $privilege) use ($resource): string {
                return $resource . ':' . $privilege;
            }, $privileges);

            $privilegeValues = array_merge($privilegeValues, $newPrivileges);
        }

        return $privilegeValues;
    }
}
