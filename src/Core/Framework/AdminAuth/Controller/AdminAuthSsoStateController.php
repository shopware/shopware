<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleAssignmentReader;
use Shopware\Core\Framework\AdminAuth\RoleMapping\SsoRoleSynchronizer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the SSO provisioning state of a user to the administration's user management: whether
 * the user is linked to an OIDC provider (has `admin_auth_oauth_identity` rows), the labels of
 * those providers, and which role assignments / admin flag are managed by the
 * {@see SsoRoleSynchronizer} (and therefore re-applied on every login).
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class AdminAuthSsoStateController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProviderRegistry $providerRegistry,
        private readonly SsoRoleAssignmentReader $roleAssignmentReader,
    ) {
    }

    #[Route(
        path: '/api/_action/admin-auth/users/{userId}/sso-state',
        name: 'api.admin_auth.users.sso_state',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['user:read']],
        methods: [Request::METHOD_GET]
    )]
    public function ssoState(string $userId): JsonResponse
    {
        if (!Feature::isActive('ADMIN_AUTH')) {
            throw AdminAuthException::featureNotActive();
        }

        if (!Uuid::isValid($userId)) {
            throw AdminAuthException::invalidUserId($userId);
        }

        $providerIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(provider_id)) FROM admin_auth_oauth_identity WHERE user_id = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        return new JsonResponse([
            'provisioned' => $providerIds !== [],
            'providerLabels' => $this->providerLabels($providerIds),
            'managedRoleIds' => $this->roleAssignmentReader->getManagedRoleIds($userId),
            'ssoManagedAdmin' => $this->roleAssignmentReader->isSsoManagedAdmin($userId),
        ]);
    }

    /**
     * Identity rows may reference providers that no longer resolve (deleted database provider, or
     * the deployment switched to YAML-managed providers); those are silently skipped.
     *
     * @param list<mixed> $providerIds
     *
     * @return list<string>
     */
    private function providerLabels(array $providerIds): array
    {
        $labels = [];
        foreach ($providerIds as $providerId) {
            $provider = $this->providerRegistry->byId((string) $providerId);
            if ($provider !== null && !\in_array($provider->label, $labels, true)) {
                $labels[] = $provider->label;
            }
        }

        return $labels;
    }
}
