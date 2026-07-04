<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Oidc\OAuthIdentityMatcher;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleAssignmentReader;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleSynchronizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Simulates consecutive OIDC logins by calling the SsoRoleSynchronizer the same way the
 * OidcVerifier does after user resolution: the sync is authoritative over its own grants
 * (tracked in admin_auth_role_assignment) and never touches manual assignments.
 *
 * @internal
 */
#[Package('framework')]
class SsoRoleSyncTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use IntegrationTestBehaviour;

    private SsoRoleSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->synchronizer = static::getContainer()->get(SsoRoleSynchronizer::class);
    }

    public function testGrantsMappedRolesWithATrackingRowOnLogin(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createAclRole('catalog-editor');
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['catalog-editor']]);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));

        static::assertTrue($this->hasAclUserRole($userId, $roleId));
        static::assertSame([$roleId], $this->trackedRoleIds($userId, $provider->providerKey));
    }

    public function testRemovesTheSyncedRoleWhenTheGroupDisappears(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createAclRole('catalog-editor');
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['catalog-editor']]);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));
        static::assertTrue($this->hasAclUserRole($userId, $roleId));

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));

        static::assertFalse($this->hasAclUserRole($userId, $roleId));
        static::assertSame([], $this->trackedRoleIds($userId, $provider->providerKey));
    }

    public function testNeverClaimsAManuallyPreAssignedRole(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createAclRole('catalog-editor');
        $this->assignRoleManually($userId, $roleId);
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['catalog-editor']]);

        // The IdP group grants the same role the admin already assigned manually.
        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));
        static::assertSame([], $this->trackedRoleIds($userId, $provider->providerKey), 'a pre-existing manual assignment must not be claimed');

        // Losing the group must not revoke the manual assignment.
        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));
        static::assertTrue($this->hasAclUserRole($userId, $roleId));
    }

    public function testManuallyAssignedRolesSurviveSyncsOfOtherRoles(): void
    {
        $userId = $this->createUser();
        $manualRoleId = $this->createAclRole('manual-role');
        $syncedRoleId = $this->createAclRole('catalog-editor');
        $this->assignRoleManually($userId, $manualRoleId);
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['catalog-editor']]);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));
        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));

        static::assertFalse($this->hasAclUserRole($userId, $syncedRoleId));
        static::assertTrue($this->hasAclUserRole($userId, $manualRoleId));
    }

    public function testGrantsAndRevokesTheAdminFlagViaTheAdminPseudoRole(): void
    {
        $userId = $this->createUser();
        $provider = $this->provider(roleMapping: ['idp-admins' => ['admin']]);
        $reader = new SsoRoleAssignmentReader($this->connection);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-admins']));

        static::assertTrue($this->isAdmin($userId));
        static::assertTrue($reader->isSsoManagedAdmin($userId));

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));

        static::assertFalse($this->isAdmin($userId));
        static::assertFalse($reader->isSsoManagedAdmin($userId));
    }

    public function testNeverRevokesAManuallySetAdminFlag(): void
    {
        $userId = $this->createUser(admin: true);
        $provider = $this->provider(roleMapping: ['idp-admins' => ['admin']]);
        $reader = new SsoRoleAssignmentReader($this->connection);

        // The flag is already set manually: the sync must not claim it ...
        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-admins']));
        static::assertFalse($reader->isSsoManagedAdmin($userId));

        // ... so losing the group must not revoke it.
        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));
        static::assertTrue($this->isAdmin($userId));
    }

    public function testDefaultRolesAreAppliedOnEveryLoginRegardlessOfGroups(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createAclRole('everyone');
        $provider = $this->provider(
            roleMapping: ['idp-catalog' => ['catalog-editor']],
            defaultRoles: ['everyone'],
        );

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));
        static::assertTrue($this->hasAclUserRole($userId, $roleId));

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: []));
        static::assertTrue($this->hasAclUserRole($userId, $roleId));
        static::assertSame([$roleId], $this->trackedRoleIds($userId, $provider->providerKey));
    }

    public function testUnknownRoleNamesAreSkippedWithoutFailingTheLogin(): void
    {
        $userId = $this->createUser();
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['no-such-role']]);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));

        static::assertSame([], $this->trackedRoleIds($userId, $provider->providerKey));
    }

    public function testProvidersManageTheirGrantsIndependently(): void
    {
        $userId = $this->createUser();
        $roleAId = $this->createAclRole('role-a');
        $roleBId = $this->createAclRole('role-b');
        $providerA = $this->provider(providerKey: 'yaml:idp_a', roleMapping: ['group-a' => ['role-a']]);
        $providerB = $this->provider(providerKey: 'yaml:idp_b', roleMapping: ['group-b' => ['role-b']]);

        $this->synchronizer->sync($userId, $providerA, $this->claims(groups: ['group-a']));
        $this->synchronizer->sync($userId, $providerB, $this->claims(groups: ['group-b']));

        // Provider A drops its grant; provider B's grant must survive.
        $this->synchronizer->sync($userId, $providerA, $this->claims(groups: []));

        static::assertFalse($this->hasAclUserRole($userId, $roleAId));
        static::assertTrue($this->hasAclUserRole($userId, $roleBId));
        static::assertSame([], $this->trackedRoleIds($userId, 'yaml:idp_a'));
        static::assertSame([$roleBId], $this->trackedRoleIds($userId, 'yaml:idp_b'));
    }

    public function testTheReaderExposesManagedRoleIds(): void
    {
        $userId = $this->createUser();
        $roleId = $this->createAclRole('catalog-editor');
        $manualRoleId = $this->createAclRole('manual-role');
        $this->assignRoleManually($userId, $manualRoleId);
        $provider = $this->provider(roleMapping: ['idp-catalog' => ['catalog-editor']]);

        $this->synchronizer->sync($userId, $provider, $this->claims(groups: ['idp-catalog']));

        $reader = new SsoRoleAssignmentReader($this->connection);
        static::assertSame([$roleId], $reader->getManagedRoleIds($userId));
        static::assertFalse($reader->isSsoManagedAdmin($userId));
    }

    public function testProvisionsWithoutTheAdminFlagWhenTheProviderManagesRoles(): void
    {
        $roleId = $this->createAclRole('catalog-editor');
        $provider = $this->provider(
            autoProvision: true,
            roleMapping: ['idp-catalog' => ['catalog-editor']],
        );
        $claims = $this->claims(groups: ['idp-catalog'], email: 'provisioned@example.com');

        // Resolve + sync, the same sequence the OidcVerifier runs.
        $matcher = static::getContainer()->get(OAuthIdentityMatcher::class);
        $userId = $matcher->resolve($provider, $claims, Context::createDefaultContext());
        $this->synchronizer->sync($userId, $provider, $claims);

        static::assertFalse($this->isAdmin($userId));
        static::assertTrue($this->hasAclUserRole($userId, $roleId));
    }

    public function testProvisionsWithTheAdminFlagWhenTheProviderDoesNotManageRoles(): void
    {
        $provider = $this->provider(autoProvision: true);
        $claims = $this->claims(email: 'legacy-provisioned@example.com');

        $matcher = static::getContainer()->get(OAuthIdentityMatcher::class);
        $userId = $matcher->resolve($provider, $claims, Context::createDefaultContext());
        $this->synchronizer->sync($userId, $provider, $claims);

        static::assertTrue($this->isAdmin($userId));
    }

    private function createUser(bool $admin = false): string
    {
        $id = Uuid::randomBytes();
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');

        $this->connection->insert('user', [
            'id' => $id,
            'locale_id' => $localeId,
            'username' => 'sync-' . Uuid::randomHex(),
            'password' => password_hash('shopware', \PASSWORD_DEFAULT),
            'first_name' => 'Sync',
            'last_name' => 'Test',
            'email' => Uuid::randomHex() . '@example.com',
            'active' => 1,
            'admin' => $admin ? 1 : 0,
            'time_zone' => 'UTC',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    private function createAclRole(string $name): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $id,
            'name' => $name,
            'privileges' => '[]',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    private function assignRoleManually(string $userId, string $roleId): void
    {
        $this->connection->insert('acl_user_role', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes($roleId),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function hasAclUserRole(string $userId, string $roleId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM acl_user_role WHERE user_id = :userId AND acl_role_id = :roleId',
            ['userId' => Uuid::fromHexToBytes($userId), 'roleId' => Uuid::fromHexToBytes($roleId)]
        ) !== false;
    }

    /**
     * @return list<string>
     */
    private function trackedRoleIds(string $userId, string $providerKey): array
    {
        $roleIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(acl_role_id))
             FROM admin_auth_role_assignment
             WHERE user_id = :userId AND provider_key = :providerKey AND is_admin_grant = 0',
            ['userId' => Uuid::fromHexToBytes($userId), 'providerKey' => $providerKey]
        );

        return array_values(array_map('strval', $roleIds));
    }

    private function isAdmin(string $userId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
    }

    /**
     * @param array<string, list<string>> $roleMapping
     * @param list<string> $defaultRoles
     */
    private function provider(
        ?string $providerKey = null,
        array $roleMapping = [],
        array $defaultRoles = [],
        bool $autoProvision = false,
    ): AdminAuthProvider {
        $id = Uuid::randomHex();

        return new AdminAuthProvider(
            id: $id,
            providerKey: $providerKey ?? $id,
            label: 'Test IdP',
            clientId: 'client',
            clientSecret: 'secret',
            autoProvision: $autoProvision,
            groupsClaim: 'groups',
            roleMapping: $roleMapping,
            defaultRoles: $defaultRoles,
        );
    }

    /**
     * @param list<string>|null $groups
     */
    private function claims(?array $groups = null, ?string $email = null): OidcClaims
    {
        return new OidcClaims(
            sub: 'idp-sub-' . Uuid::randomHex(),
            email: $email ?? 'user-' . Uuid::randomHex() . '@example.com',
            emailVerified: true,
            name: 'Sync Test',
            preferredUsername: $email !== null ? 'user-' . Uuid::randomHex() : null,
            claims: $groups === null ? [] : ['groups' => $groups],
        );
    }
}
