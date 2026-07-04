<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\RoleMapping;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Authoritative OIDC group -> ACL role synchronization, executed on every OIDC login.
 *
 * The provider maps IdP groups to acl_role names ({@see AdminAuthProvider::$roleMapping}) and can
 * grant roles unconditionally ({@see AdminAuthProvider::$defaultRoles}). The reserved pseudo-role
 * 'admin' controls the `user.admin` flag instead of an acl_role.
 *
 * The sync is authoritative only over grants it created itself, tracked in
 * `admin_auth_role_assignment`: a role (or admin flag) that already exists without a tracking row
 * is treated as a manual assignment — it is never claimed and never revoked.
 *
 * @internal
 */
#[Package('framework')]
class SsoRoleSynchronizer
{
    /**
     * Reserved role name that toggles the `user.admin` flag instead of an acl_role assignment.
     */
    final public const ADMIN_PSEUDO_ROLE = 'admin';

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $userId the resolved user id as a hex string
     */
    public function sync(string $userId, AdminAuthProvider $provider, OidcClaims $claims): void
    {
        if ($provider->groupsClaim === null && $provider->roleMapping === [] && $provider->defaultRoles === []) {
            // The provider does not manage roles at all: leave every assignment alone.
            return;
        }

        $desired = $this->computeDesiredAssignments($provider, $claims);

        RetryableTransaction::retryable($this->connection, function (Connection $connection) use ($userId, $provider, $desired): void {
            $this->apply($connection, $userId, $provider->providerKey, $desired['roleNames'], $desired['admin']);
        });
    }

    /**
     * Computes what the provider wants the user to have: the union of the mapped roles for the
     * user's groups plus the provider's default roles. The 'admin' pseudo-role is split off into
     * the admin flag.
     *
     * @return array{roleNames: list<string>, admin: bool}
     */
    public function computeDesiredAssignments(AdminAuthProvider $provider, OidcClaims $claims): array
    {
        $roleNames = $provider->defaultRoles;

        foreach ($this->extractGroups($provider, $claims) as $group) {
            $roleNames = array_merge($roleNames, $provider->roleMapping[$group] ?? []);
        }

        $roleNames = array_values(array_unique($roleNames));
        $admin = \in_array(self::ADMIN_PSEUDO_ROLE, $roleNames, true);

        return [
            'roleNames' => array_values(array_filter($roleNames, static fn (string $name): bool => $name !== self::ADMIN_PSEUDO_ROLE)),
            'admin' => $admin,
        ];
    }

    /**
     * @param list<string> $desiredRoleNames
     */
    private function apply(Connection $connection, string $userId, string $providerKey, array $desiredRoleNames, bool $desiredAdmin): void
    {
        $userIdBytes = Uuid::fromHexToBytes($userId);
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');

        $desiredRoleIds = $this->resolveRoleIds($connection, $desiredRoleNames);

        $managedRows = $connection->fetchAllAssociative(
            'SELECT LOWER(HEX(acl_role_id)) AS role_id, is_admin_grant
             FROM admin_auth_role_assignment
             WHERE user_id = :userId AND provider_key = :providerKey',
            ['userId' => $userIdBytes, 'providerKey' => $providerKey]
        );

        $managedRoleIds = [];
        $hasManagedAdminGrant = false;
        foreach ($managedRows as $row) {
            if ((bool) $row['is_admin_grant']) {
                $hasManagedAdminGrant = true;
            } elseif ($row['role_id'] !== null) {
                $managedRoleIds[] = (string) $row['role_id'];
            }
        }

        foreach (array_diff($desiredRoleIds, $managedRoleIds) as $roleId) {
            $roleIdBytes = Uuid::fromHexToBytes($roleId);

            $alreadyAssigned = $connection->fetchOne(
                'SELECT 1 FROM acl_user_role WHERE user_id = :userId AND acl_role_id = :roleId',
                ['userId' => $userIdBytes, 'roleId' => $roleIdBytes]
            ) !== false;

            if ($alreadyAssigned) {
                // Pre-existing assignment without a tracking row = manual: never claim it, so a
                // later group change can never revoke it.
                continue;
            }

            $connection->insert('acl_user_role', [
                'user_id' => $userIdBytes,
                'acl_role_id' => $roleIdBytes,
                'created_at' => $now,
            ]);
            $connection->insert('admin_auth_role_assignment', [
                'id' => Uuid::randomBytes(),
                'user_id' => $userIdBytes,
                'provider_key' => $providerKey,
                'acl_role_id' => $roleIdBytes,
                'is_admin_grant' => 0,
                'created_at' => $now,
            ]);
        }

        foreach (array_diff($managedRoleIds, $desiredRoleIds) as $roleId) {
            $roleIdBytes = Uuid::fromHexToBytes($roleId);

            $connection->executeStatement(
                'DELETE FROM acl_user_role WHERE user_id = :userId AND acl_role_id = :roleId',
                ['userId' => $userIdBytes, 'roleId' => $roleIdBytes]
            );
            $connection->executeStatement(
                'DELETE FROM admin_auth_role_assignment
                 WHERE user_id = :userId AND provider_key = :providerKey AND acl_role_id = :roleId',
                ['userId' => $userIdBytes, 'providerKey' => $providerKey, 'roleId' => $roleIdBytes]
            );
        }

        $this->applyAdminFlag($connection, $userIdBytes, $providerKey, $desiredAdmin, $hasManagedAdminGrant, $now);
    }

    private function applyAdminFlag(Connection $connection, string $userIdBytes, string $providerKey, bool $desiredAdmin, bool $hasManagedAdminGrant, string $now): void
    {
        if ($desiredAdmin && !$hasManagedAdminGrant) {
            // Only claim the flag when this sync actually flips it: an already-set admin flag
            // without a tracking row is a manual grant and stays out of reach.
            $updated = $connection->executeStatement(
                'UPDATE `user` SET admin = 1 WHERE id = :userId AND admin = 0',
                ['userId' => $userIdBytes]
            );

            if ($updated === 1) {
                $connection->insert('admin_auth_role_assignment', [
                    'id' => Uuid::randomBytes(),
                    'user_id' => $userIdBytes,
                    'provider_key' => $providerKey,
                    'acl_role_id' => null,
                    'is_admin_grant' => 1,
                    'created_at' => $now,
                ]);
            }

            return;
        }

        if (!$desiredAdmin && $hasManagedAdminGrant) {
            $connection->executeStatement(
                'UPDATE `user` SET admin = 0 WHERE id = :userId',
                ['userId' => $userIdBytes]
            );
            $connection->executeStatement(
                'DELETE FROM admin_auth_role_assignment
                 WHERE user_id = :userId AND provider_key = :providerKey AND is_admin_grant = 1',
                ['userId' => $userIdBytes, 'providerKey' => $providerKey]
            );
        }
    }

    /**
     * Resolves acl_role names to hex ids. Unknown names are logged and skipped.
     *
     * @param list<string> $roleNames
     *
     * @return list<string>
     */
    private function resolveRoleIds(Connection $connection, array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        /** @var array<string, string> $idsByName */
        $idsByName = $connection->fetchAllKeyValue(
            'SELECT name, LOWER(HEX(id)) FROM acl_role WHERE name IN (:names) AND deleted_at IS NULL',
            ['names' => $roleNames],
            ['names' => ArrayParameterType::STRING]
        );

        $unknown = array_values(array_diff($roleNames, array_keys($idsByName)));
        if ($unknown !== []) {
            $this->logger->warning('Admin auth role sync: skipping unknown acl_role names configured in the provider role mapping.', [
                'roles' => $unknown,
            ]);
        }

        return array_values($idsByName);
    }

    /**
     * The groups claim is expected to be a list of strings; some IdPs send a single group as a
     * plain string. Anything else counts as "no groups".
     *
     * @return list<string>
     */
    private function extractGroups(AdminAuthProvider $provider, OidcClaims $claims): array
    {
        if ($provider->groupsClaim === null) {
            return [];
        }

        $value = $claims->getClaim($provider->groupsClaim);

        if (\is_string($value)) {
            return [$value];
        }

        if (\is_array($value)) {
            return array_values(array_filter($value, is_string(...)));
        }

        $this->logger->debug('Admin auth role sync: groups claim is missing or has an unexpected type, treating as empty.', [
            'claim' => $provider->groupsClaim,
            'type' => get_debug_type($value),
        ]);

        return [];
    }
}
