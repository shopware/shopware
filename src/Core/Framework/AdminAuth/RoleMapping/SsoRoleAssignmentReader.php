<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\RoleMapping;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\SsoRoleSyncTest;

/**
 * Read access to the SSO-managed state of a user's role assignments, i.e. the grants created by
 * the {@see SsoRoleSynchronizer}. Used by the admin UI to render synced roles read-only.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see SsoRoleSyncTest
 */
#[Package('framework')]
class SsoRoleAssignmentReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param string $userId the user id as a hex string
     *
     * @return list<string> the acl_role ids (hex) currently managed by the SSO role sync
     */
    public function getManagedRoleIds(string $userId): array
    {
        $roleIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(acl_role_id))
             FROM admin_auth_role_assignment
             WHERE user_id = :userId AND is_admin_grant = 0 AND acl_role_id IS NOT NULL',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        return array_values(array_map('strval', $roleIds));
    }

    /**
     * @param string $userId the user id as a hex string
     */
    public function isSsoManagedAdmin(string $userId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM admin_auth_role_assignment WHERE user_id = :userId AND is_admin_grant = 1',
            ['userId' => Uuid::fromHexToBytes($userId)]
        ) !== false;
    }
}
