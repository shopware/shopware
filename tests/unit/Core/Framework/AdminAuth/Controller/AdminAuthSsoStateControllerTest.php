<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Controller\AdminAuthSsoStateController;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleAssignmentReader;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(AdminAuthSsoStateController::class)]
class AdminAuthSsoStateControllerTest extends TestCase
{
    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testTheRouteRequiresTheFeatureFlag(): void
    {
        $this->expectExceptionObject(AdminAuthException::featureNotActive());

        $this->createController([])->ssoState(Uuid::randomHex());
    }

    public function testAnInvalidUserIdIsRejected(): void
    {
        $this->expectExceptionObject(AdminAuthException::invalidUserId('not-a-uuid'));

        $this->createController([])->ssoState('not-a-uuid');
    }

    public function testProvisionedUserExposesDeduplicatedProviderLabelsAndManagedAssignments(): void
    {
        $provider = $this->provider();
        // Two identities for the same provider plus one for a provider that no longer exists.
        $identityProviderIds = [$provider->id, Uuid::randomHex(), $provider->id];

        $reader = static::createStub(SsoRoleAssignmentReader::class);
        $reader->method('getManagedRoleIds')->willReturn(['role-id-1']);
        $reader->method('isSsoManagedAdmin')->willReturn(true);

        $response = $this->createController($identityProviderIds, $provider, $reader)->ssoState(Uuid::randomHex());

        static::assertSame([
            'provisioned' => true,
            'providerLabels' => ['Corporate SSO'],
            'managedRoleIds' => ['role-id-1'],
            'ssoManagedAdmin' => true,
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testUnprovisionedUserReportsAnEmptyState(): void
    {
        $response = $this->createController([])->ssoState(Uuid::randomHex());

        static::assertSame([
            'provisioned' => false,
            'providerLabels' => [],
            'managedRoleIds' => [],
            'ssoManagedAdmin' => false,
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $identityProviderIds
     */
    private function createController(
        array $identityProviderIds,
        ?AdminAuthProvider $provider = null,
        ?SsoRoleAssignmentReader $reader = null,
    ): AdminAuthSsoStateController {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($identityProviderIds);

        $registry = static::createStub(ProviderRegistry::class);
        $registry->method('byId')->willReturnCallback(
            static fn (string $id): ?AdminAuthProvider => $provider !== null && $id === $provider->id ? $provider : null
        );

        if ($reader === null) {
            $reader = static::createStub(SsoRoleAssignmentReader::class);
            $reader->method('getManagedRoleIds')->willReturn([]);
            $reader->method('isSsoManagedAdmin')->willReturn(false);
        }

        return new AdminAuthSsoStateController($connection, $registry, $reader);
    }

    private function provider(): AdminAuthProvider
    {
        return new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );
    }
}
