<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Entity\OauthIdentity\AdminAuthOauthIdentityCollection;
use Shopware\Core\Framework\AdminAuth\Oidc\OAuthIdentityMatcher;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(OAuthIdentityMatcher::class)]
class OAuthIdentityMatcherTest extends TestCase
{
    public function testAnExistingIdentityLinkWinsAndIsTouched(): void
    {
        $userId = Uuid::randomHex();
        $identityId = Uuid::randomHex();
        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([[$identityId]]);

        $matcher = new OAuthIdentityMatcher(
            $this->connection(linkedUserId: $userId),
            $identityRepository,
            new MockClock()
        );

        $resolved = $matcher->resolve($this->provider(), $this->claims(), Context::createDefaultContext());

        static::assertSame($userId, $resolved);
        static::assertSame([
            'id' => $identityId,
            'providerId' => 'a5b4885a89694a4c8e28e00b48b09dcc',
            'userId' => $userId,
            'sub' => 'idp-sub-1',
            'email' => 'jane@corp.example',
        ], $identityRepository->upserts[0][0]);
    }

    public function testWithoutALinkAVerifiedEmailIsRequired(): void
    {
        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([]);
        $matcher = new OAuthIdentityMatcher($this->connection(), $identityRepository, new MockClock());

        $this->expectExceptionObject(AdminAuthException::oidcLoginFailed(
            'a verified email is required to match or provision an admin user'
        ));

        $matcher->resolve($this->provider(), $this->claims(emailVerified: false), Context::createDefaultContext());
    }

    public function testAnEmailMatchLinksTheIdentityToTheExistingUser(): void
    {
        $userId = Uuid::randomHex();
        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([[]]);

        $matcher = new OAuthIdentityMatcher(
            $this->connection(emailUserId: $userId),
            $identityRepository,
            new MockClock()
        );

        $resolved = $matcher->resolve($this->provider(), $this->claims(), Context::createDefaultContext());

        static::assertSame($userId, $resolved);

        $upsert = $identityRepository->upserts[0][0];
        static::assertIsString($upsert['id']);
        static::assertTrue(Uuid::isValid($upsert['id']), 'a fresh identity link must get a new id');
        static::assertSame($userId, $upsert['userId']);
        static::assertSame('idp-sub-1', $upsert['sub']);
    }

    public function testAnUnknownEmailWithoutAutoProvisioningIsRejected(): void
    {
        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([]);
        $matcher = new OAuthIdentityMatcher($this->connection(), $identityRepository, new MockClock());

        $this->expectExceptionObject(AdminAuthException::oidcLoginFailed(
            'no admin user matches the OIDC email and auto-provisioning is disabled'
        ));

        $matcher->resolve($this->provider(autoProvision: false), $this->claims(), Context::createDefaultContext());
    }

    public function testAutoProvisioningWithoutRoleManagementCreatesALegacyAdminUser(): void
    {
        $clock = new MockClock('2026-01-01 12:00:00');
        $insertedRow = null;

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback($this->fetchOneCallback(localeId: 'locale-id-bytes'));
        $connection->expects($this->once())
            ->method('insert')
            ->with('user', static::callback(static function (array $row) use (&$insertedRow): bool {
                $insertedRow = $row;

                return true;
            }));

        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([[]]);
        $matcher = new OAuthIdentityMatcher($connection, $identityRepository, $clock);

        $resolved = $matcher->resolve(
            $this->provider(autoProvision: true),
            $this->claims(),
            Context::createDefaultContext()
        );

        static::assertIsArray($insertedRow);
        static::assertSame($resolved, Uuid::fromBytesToHex($insertedRow['id']));
        static::assertSame('locale-id-bytes', $insertedRow['locale_id']);
        static::assertSame('jane', $insertedRow['username']);
        static::assertSame('Jane', $insertedRow['first_name']);
        static::assertSame('Doe', $insertedRow['last_name']);
        static::assertSame('jane@corp.example', $insertedRow['email']);
        static::assertSame(1, $insertedRow['active']);
        static::assertSame(1, $insertedRow['admin'], 'without role management the user must get the admin flag');
        static::assertSame('2026-01-01 12:00:00.000', $insertedRow['created_at']);
        static::assertIsString($insertedRow['password']);
        static::assertNotSame('', $insertedRow['password']);

        static::assertSame($resolved, $identityRepository->upserts[0][0]['userId']);
    }

    public function testAutoProvisioningWithRoleManagementCreatesANonAdminUser(): void
    {
        $insertedRow = null;

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback($this->fetchOneCallback(localeId: 'locale-id-bytes'));
        $connection->expects($this->once())
            ->method('insert')
            ->with('user', static::callback(static function (array $row) use (&$insertedRow): bool {
                $insertedRow = $row;

                return true;
            }));

        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([[]]);
        $matcher = new OAuthIdentityMatcher($connection, $identityRepository, new MockClock());

        $matcher->resolve(
            $this->provider(autoProvision: true, roleMapping: ['idp-admins' => ['admin']]),
            $this->claims(name: null, preferredUsername: 'jonny'),
            Context::createDefaultContext()
        );

        static::assertIsArray($insertedRow);
        static::assertSame(0, $insertedRow['admin'], 'the SsoRoleSynchronizer manages the admin flag for role-mapped providers');
        static::assertSame('jonny', $insertedRow['username']);
        static::assertSame('jonny', $insertedRow['first_name'], 'without a name claim the preferred username is used');
        static::assertSame('jonny', $insertedRow['last_name']);
    }

    public function testProvisioningFailsWhenNoLocaleExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback($this->fetchOneCallback(localeId: null));
        $connection->expects($this->never())->method('insert');

        /** @var StaticEntityRepository<AdminAuthOauthIdentityCollection> $identityRepository */
        $identityRepository = new StaticEntityRepository([]);
        $matcher = new OAuthIdentityMatcher($connection, $identityRepository, new MockClock());

        $this->expectExceptionObject(AdminAuthException::oidcLoginFailed('no locale available to provision the user'));

        $matcher->resolve($this->provider(autoProvision: true), $this->claims(), Context::createDefaultContext());
    }

    private function connection(?string $linkedUserId = null, ?string $emailUserId = null): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            $this->fetchOneCallback($linkedUserId, $emailUserId)
        );

        return $connection;
    }

    private function fetchOneCallback(
        ?string $linkedUserId = null,
        ?string $emailUserId = null,
        ?string $localeId = null,
    ): \Closure {
        return static function (string $sql) use ($linkedUserId, $emailUserId, $localeId): string|false {
            if (str_contains($sql, 'admin_auth_oauth_identity')) {
                return $linkedUserId ?? false;
            }

            if (str_contains($sql, 'FROM `user`')) {
                return $emailUserId ?? false;
            }

            if (str_contains($sql, 'FROM locale')) {
                return $localeId ?? false;
            }

            return false;
        };
    }

    /**
     * @param array<string, list<string>> $roleMapping
     */
    private function provider(bool $autoProvision = false, array $roleMapping = []): AdminAuthProvider
    {
        return new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            autoProvision: $autoProvision,
            roleMapping: $roleMapping,
        );
    }

    private function claims(
        bool $emailVerified = true,
        ?string $name = 'Jane Doe',
        ?string $preferredUsername = 'jane',
    ): OidcClaims {
        return new OidcClaims(
            sub: 'idp-sub-1',
            email: 'jane@corp.example',
            emailVerified: $emailVerified,
            name: $name,
            preferredUsername: $preferredUsername,
        );
    }
}
