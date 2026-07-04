<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Oidc\OAuthIdentityMatcher;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class OAuthIdentityMatcherTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use IntegrationTestBehaviour;

    private OAuthIdentityMatcher $matcher;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->matcher = static::getContainer()->get(OAuthIdentityMatcher::class);
        $this->context = Context::createDefaultContext();
    }

    public function testResolvesAnExistingIdentityLinkBySub(): void
    {
        $provider = $this->provider();
        $userId = $this->fetchAdminUserId();

        $this->connection->insert('admin_auth_oauth_identity', [
            'id' => Uuid::randomBytes(),
            'provider_id' => Uuid::fromHexToBytes($provider->id),
            'user_id' => Uuid::fromHexToBytes($userId),
            'sub' => 'idp-sub-42',
            'email' => 'linked@example.com',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        $resolved = $this->matcher->resolve($provider, $this->claims(sub: 'idp-sub-42', email: null, emailVerified: false), $this->context);

        static::assertSame($userId, $resolved);
    }

    public function testMatchesByVerifiedEmailAndCreatesTheIdentityLink(): void
    {
        $provider = $this->provider();
        $userId = $this->fetchAdminUserId();

        $email = $this->connection->fetchOne(
            'SELECT email FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
        static::assertIsString($email);

        $resolved = $this->matcher->resolve($provider, $this->claims(sub: 'idp-sub-1', email: $email), $this->context);

        static::assertSame($userId, $resolved);

        $link = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(user_id)) AS user_id, email FROM admin_auth_oauth_identity WHERE provider_id = :provider AND sub = :sub',
            ['provider' => Uuid::fromHexToBytes($provider->id), 'sub' => 'idp-sub-1']
        );

        static::assertIsArray($link, 'a successful email match must persist the (provider, sub) link');
        static::assertSame($userId, $link['user_id']);
        static::assertSame($email, $link['email']);

        // The next login resolves via the link even if the email changed at the IdP.
        $resolvedAgain = $this->matcher->resolve($provider, $this->claims(sub: 'idp-sub-1', email: 'changed@example.com'), $this->context);
        static::assertSame($userId, $resolvedAgain);
    }

    public function testRejectsAnUnverifiedEmail(): void
    {
        $this->expectExceptionObject(
            AdminAuthException::oidcLoginFailed('a verified email is required to match or provision an admin user')
        );

        $this->matcher->resolve(
            $this->provider(),
            $this->claims(sub: 'idp-sub-2', email: 'someone@example.com', emailVerified: false),
            $this->context
        );
    }

    public function testRejectsAnUnknownUserWhenAutoProvisioningIsDisabled(): void
    {
        $this->expectExceptionObject(
            AdminAuthException::oidcLoginFailed('no admin user matches the OIDC email and auto-provisioning is disabled')
        );

        $this->matcher->resolve(
            $this->provider(autoProvision: false),
            $this->claims(sub: 'idp-sub-3', email: 'unknown@example.com'),
            $this->context
        );
    }

    public function testProvisionsAnAdminUserFromTheClaimsWhenTheProviderDoesNotManageRoles(): void
    {
        $provider = $this->provider(autoProvision: true);

        $resolved = $this->matcher->resolve(
            $provider,
            $this->claims(sub: 'idp-sub-4', email: 'jane.doe@example.com', name: 'Jane Doe', preferredUsername: 'jane'),
            $this->context
        );

        $user = $this->connection->fetchAssociative(
            'SELECT username, first_name, last_name, email, active, admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($resolved)]
        );

        static::assertIsArray($user);
        static::assertSame('jane', $user['username']);
        static::assertSame('Jane', $user['first_name']);
        static::assertSame('Doe', $user['last_name']);
        static::assertSame('jane.doe@example.com', $user['email']);
        static::assertSame(1, (int) $user['active']);
        static::assertSame(1, (int) $user['admin']);

        $linkedUserId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(user_id)) FROM admin_auth_oauth_identity WHERE provider_id = :provider AND sub = :sub',
            ['provider' => Uuid::fromHexToBytes($provider->id), 'sub' => 'idp-sub-4']
        );
        static::assertSame($resolved, $linkedUserId);
    }

    public function testProvisionsWithoutTheAdminFlagWhenTheProviderManagesRoles(): void
    {
        // user.admin = 1 bypasses ACL entirely; a role-managing provider expects the
        // SsoRoleSynchronizer to assign the mapped roles right after resolution instead.
        $provider = $this->provider(autoProvision: true, roleMapping: ['idp-catalog' => ['catalog-editor']]);

        $resolved = $this->matcher->resolve(
            $provider,
            $this->claims(sub: 'idp-sub-5', email: 'john.doe@example.com', name: 'John Doe'),
            $this->context
        );

        $admin = $this->connection->fetchOne(
            'SELECT admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($resolved)]
        );
        static::assertSame(0, (int) $admin);
    }

    public function testProvisionsWithoutTheAdminFlagWhenTheProviderGrantsDefaultRoles(): void
    {
        $provider = $this->provider(autoProvision: true, defaultRoles: ['catalog-editor']);

        $resolved = $this->matcher->resolve(
            $provider,
            $this->claims(sub: 'idp-sub-6', email: 'jim.doe@example.com', name: 'Jim Doe'),
            $this->context
        );

        $admin = $this->connection->fetchOne(
            'SELECT admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($resolved)]
        );
        static::assertSame(0, (int) $admin);
    }

    /**
     * @param array<string, list<string>> $roleMapping
     * @param list<string> $defaultRoles
     */
    private function provider(bool $autoProvision = false, array $roleMapping = [], array $defaultRoles = []): AdminAuthProvider
    {
        $id = Uuid::randomHex();

        return new AdminAuthProvider(
            id: $id,
            providerKey: $id,
            label: 'Test IdP',
            clientId: 'client',
            clientSecret: 'secret',
            autoProvision: $autoProvision,
            roleMapping: $roleMapping,
            defaultRoles: $defaultRoles,
        );
    }

    private function claims(
        string $sub,
        ?string $email,
        bool $emailVerified = true,
        ?string $name = null,
        ?string $preferredUsername = null,
    ): OidcClaims {
        return new OidcClaims(
            sub: $sub,
            email: $email,
            emailVerified: $emailVerified,
            name: $name,
            preferredUsername: $preferredUsername,
        );
    }
}
