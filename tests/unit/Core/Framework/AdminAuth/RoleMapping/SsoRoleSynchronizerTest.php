<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\RoleMapping;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleSynchronizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\SsoRoleSyncTest;
use Symfony\Component\Clock\MockClock;

/**
 * Covers the pure desired-set computation; the database behavior (grant/revoke/manual-untouched)
 * is covered by the integration test {@see SsoRoleSyncTest}.
 *
 * @internal
 */
#[CoversClass(SsoRoleSynchronizer::class)]
class SsoRoleSynchronizerTest extends TestCase
{
    public function testDesiredRolesAreTheUnionOfMappedGroupsAndDefaultRoles(): void
    {
        $provider = $this->provider(
            groupsClaim: 'groups',
            roleMapping: [
                'idp-catalog' => ['catalog-editor'],
                'idp-support' => ['order-viewer', 'catalog-editor'],
                'idp-unrelated' => ['never-granted'],
            ],
            defaultRoles: ['everyone'],
        );

        $desired = $this->synchronizer()->computeDesiredAssignments(
            $provider,
            $this->claims(['groups' => ['idp-catalog', 'idp-support', 'idp-unmapped']])
        );

        static::assertSame(['everyone', 'catalog-editor', 'order-viewer'], $desired['roleNames']);
        static::assertFalse($desired['admin']);
    }

    public function testAScalarGroupsClaimIsTreatedAsSingleElementList(): void
    {
        $provider = $this->provider(
            groupsClaim: 'group',
            roleMapping: ['idp-catalog' => ['catalog-editor']],
        );

        $desired = $this->synchronizer()->computeDesiredAssignments(
            $provider,
            $this->claims(['group' => 'idp-catalog'])
        );

        static::assertSame(['catalog-editor'], $desired['roleNames']);
    }

    public function testAnUnexpectedGroupsClaimTypeYieldsNoGroupsButKeepsDefaultRoles(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');

        $provider = $this->provider(
            groupsClaim: 'groups',
            roleMapping: ['idp-catalog' => ['catalog-editor']],
            defaultRoles: ['everyone'],
        );

        $desired = $this->synchronizer($logger)->computeDesiredAssignments(
            $provider,
            $this->claims(['groups' => 42])
        );

        static::assertSame(['everyone'], $desired['roleNames']);
        static::assertFalse($desired['admin']);
    }

    public function testTheAdminPseudoRoleBecomesTheAdminFlagInsteadOfARoleName(): void
    {
        $provider = $this->provider(
            groupsClaim: 'groups',
            roleMapping: ['idp-admins' => ['admin', 'catalog-editor']],
        );

        $desired = $this->synchronizer()->computeDesiredAssignments(
            $provider,
            $this->claims(['groups' => ['idp-admins']])
        );

        static::assertSame(['catalog-editor'], $desired['roleNames']);
        static::assertTrue($desired['admin']);
    }

    public function testAdminCanAlsoBeGrantedAsDefaultRole(): void
    {
        $provider = $this->provider(defaultRoles: ['admin']);

        $desired = $this->synchronizer()->computeDesiredAssignments($provider, $this->claims());

        static::assertSame([], $desired['roleNames']);
        static::assertTrue($desired['admin']);
    }

    public function testSyncIsANoOpForProvidersWithoutAnyRoleManagement(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method(static::anything());

        $synchronizer = new SsoRoleSynchronizer($connection, new MockClock(), $this->createMock(LoggerInterface::class));

        $synchronizer->sync(Uuid::randomHex(), $this->provider(), $this->claims(['groups' => ['idp-catalog']]));
    }

    public function testUnknownRoleNamesAreSkippedWithAWarning(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $closure) => $closure($connection));
        // No acl_role matches the configured names, no assignments are managed yet.
        $connection->method('fetchAllKeyValue')->willReturn([]);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::anything(), ['roles' => ['does-not-exist']]);

        $synchronizer = new SsoRoleSynchronizer($connection, new MockClock(), $logger);

        $synchronizer->sync(
            Uuid::randomHex(),
            $this->provider(defaultRoles: ['does-not-exist']),
            $this->claims()
        );
    }

    private function synchronizer(?LoggerInterface $logger = null): SsoRoleSynchronizer
    {
        return new SsoRoleSynchronizer(
            $this->createMock(Connection::class),
            new MockClock(),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @param array<string, list<string>> $roleMapping
     * @param list<string> $defaultRoles
     */
    private function provider(
        ?string $groupsClaim = null,
        array $roleMapping = [],
        array $defaultRoles = [],
    ): AdminAuthProvider {
        $id = Uuid::randomHex();

        return new AdminAuthProvider(
            id: $id,
            providerKey: $id,
            label: 'Test IdP',
            clientId: 'client',
            clientSecret: 'secret',
            groupsClaim: $groupsClaim,
            roleMapping: $roleMapping,
            defaultRoles: $defaultRoles,
        );
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function claims(array $claims = []): OidcClaims
    {
        return new OidcClaims(
            sub: 'idp-sub-1',
            email: 'user@example.com',
            emailVerified: true,
            name: 'Test User',
            preferredUsername: 'test',
            claims: $claims,
        );
    }
}
