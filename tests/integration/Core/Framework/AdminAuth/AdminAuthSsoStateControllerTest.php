<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminAuthSsoStateControllerTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testRouteIsNotAvailableWithoutTheFeature(): void
    {
        Feature::skipTestIfActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/users/' . Uuid::randomHex() . '/sso-state');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testAnInvalidUserIdIsRejected(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/users/not-a-uuid/sso-state');

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());
    }

    public function testAPlainUserIsNotProvisioned(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $userId = $this->fetchAdminUserId();

        $data = $this->requestSsoState($this->getBrowser(), $userId);

        static::assertFalse($data['provisioned']);
        static::assertSame([], $data['providerLabels']);
        static::assertSame([], $data['managedRoleIds']);
        static::assertFalse($data['ssoManagedAdmin']);
    }

    public function testAProvisionedUserExposesProvidersAndManagedAssignments(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $userId = $this->fetchAdminUserId();
        $providerId = $this->insertProvider('Corporate SSO');
        $this->linkIdentity($userId, $providerId);

        $roleId = $this->insertAclRole('catalog-editor');
        $this->insertRoleAssignment($userId, $providerId, aclRoleId: $roleId);
        $this->insertRoleAssignment($userId, $providerId, isAdminGrant: true);

        $data = $this->requestSsoState($this->getBrowser(), $userId);

        static::assertTrue($data['provisioned']);
        static::assertSame(['Corporate SSO'], $data['providerLabels']);
        static::assertSame([$roleId], $data['managedRoleIds']);
        static::assertTrue($data['ssoManagedAdmin']);
    }

    public function testAnIdentityOfAnUnresolvableProviderStillCountsAsProvisioned(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $userId = $this->fetchAdminUserId();
        // Identity row pointing at a provider that no longer exists (e.g. deleted via the admin UI).
        $this->linkIdentity($userId, Uuid::randomHex());

        $data = $this->requestSsoState($this->getBrowser(), $userId);

        static::assertTrue($data['provisioned']);
        static::assertSame([], $data['providerLabels']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSsoState(TestBrowser $browser, string $userId): array
    {
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/users/' . $userId . '/sso-state');

        $response = $browser->getResponse();
        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsBool($data['provisioned']);
        static::assertIsArray($data['providerLabels']);
        static::assertIsArray($data['managedRoleIds']);
        static::assertIsBool($data['ssoManagedAdmin']);

        return $data;
    }

    private function insertProvider(string $name): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('admin_auth_provider', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => $name,
            'type' => 'oidc',
            'active' => 1,
            'is_primary' => 1,
            'config' => json_encode([
                'clientId' => 'the-client-id',
                'authorizationEndpoint' => 'https://idp.invalid/authorize',
                'tokenEndpoint' => 'https://idp.invalid/token',
                'jwksUri' => 'https://idp.invalid/jwks',
            ], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    private function linkIdentity(string $userId, string $providerId): void
    {
        $this->connection->insert('admin_auth_oauth_identity', [
            'id' => Uuid::randomBytes(),
            'provider_id' => Uuid::fromHexToBytes($providerId),
            'user_id' => Uuid::fromHexToBytes($userId),
            'sub' => 'sub-' . Uuid::randomHex(),
            'email' => 'sso-user@example.com',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function insertAclRole(string $name): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => $name,
            'privileges' => '[]',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    private function insertRoleAssignment(
        string $userId,
        string $providerId,
        ?string $aclRoleId = null,
        bool $isAdminGrant = false,
    ): void {
        $this->connection->insert('admin_auth_role_assignment', [
            'id' => Uuid::randomBytes(),
            'user_id' => Uuid::fromHexToBytes($userId),
            'provider_key' => $providerId,
            'acl_role_id' => $aclRoleId !== null ? Uuid::fromHexToBytes($aclRoleId) : null,
            'is_admin_grant' => $isAdminGrant ? 1 : 0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
