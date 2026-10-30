<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\Acl\AclRoleLoaderTest
 */
#[Package('framework')]
class AclRoleLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * The roles of the given users and integrations. Deleted ones are left out.
     *
     * @param list<string> $userIds
     * @param list<string> $integrationIds
     */
    public function getRoles(array $userIds, array $integrationIds): IdentityRoleMap
    {
        [$integrationRoles, $appIntegrationIds] = $this->integrationRoles($integrationIds);

        return new IdentityRoleMap($this->userRoles($userIds), $integrationRoles, $appIntegrationIds);
    }

    /**
     * @param list<string> $roleIds
     *
     * @return array<string, AclPrivilegeCollection>
     */
    public function getPrivilegesForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $roles = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `id`, `privileges`
                FROM `acl_role`
                WHERE `id` IN (:aclRoleIds)
            SQL,
            ['aclRoleIds' => Uuid::fromHexToBytesList($roleIds)],
            ['aclRoleIds' => ArrayParameterType::BINARY]
        );

        if (!$roles) {
            return [];
        }

        $privileges = [];
        foreach ($roles as $privilege) {
            $privileges[Uuid::fromBytesToHex($privilege['id'])]
                = new AclPrivilegeCollection(json_decode((string) $privilege['privileges'], true, 512, \JSON_THROW_ON_ERROR));
        }

        return $privileges;
    }

    /**
     * @param list<string> $userIds
     *
     * @return array<string, AclRoles> keyed by user id
     */
    private function userRoles(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->toRoles($this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(u.id)) as id, u.admin as isAdmin, LOWER(HEX(ur.acl_role_id)) as roleId
             FROM user u
             LEFT JOIN acl_user_role ur ON ur.user_id = u.id
             WHERE u.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($userIds)],
            ['ids' => ArrayParameterType::BINARY]
        ));
    }

    /**
     * @param list<string> $integrationIds
     *
     * @return array{array<string, AclRoles>, list<string>} the roles keyed by integration id, and the ids of the app integrations
     */
    private function integrationRoles(array $integrationIds): array
    {
        if ($integrationIds === []) {
            return [[], []];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(i.id)) as id, a.id IS NULL AND i.admin as isAdmin, a.id IS NOT NULL as isAppIntegration, LOWER(HEX(IF(a.id IS NULL, ir.acl_role_id, a.acl_role_id))) as roleId
             FROM integration i
             LEFT JOIN app a ON a.integration_id = i.id AND a.acl_role_id IS NOT NULL
             LEFT JOIN integration_role ir ON ir.integration_id = i.id AND a.id IS NULL
             WHERE i.id IN (:ids)
               AND i.deleted_at IS NULL',
            ['ids' => Uuid::fromHexToBytesList($integrationIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $appIntegrationIds = array_column(array_filter($rows, static fn (array $row): bool => (bool) $row['isAppIntegration']), 'id');

        return [$this->toRoles($rows), $appIntegrationIds];
    }

    /**
     * @param list<array<string, mixed>> $rows one row per role, with id, isAdmin and roleId
     *
     * @return array<string, AclRoles> keyed by id
     */
    private function toRoles(array $rows): array
    {
        $unrestricted = [];
        $roleIds = [];
        foreach ($rows as $row) {
            $unrestricted[$row['id']] = (bool) $row['isAdmin'];
            $roleIds[$row['id']] ??= [];

            if ($row['roleId'] !== null) {
                $roleIds[$row['id']][] = $row['roleId'];
            }
        }

        $roles = [];
        foreach ($unrestricted as $id => $isUnrestricted) {
            $roles[$id] = new AclRoles($isUnrestricted, $roleIds[$id]);
        }

        return $roles;
    }
}
