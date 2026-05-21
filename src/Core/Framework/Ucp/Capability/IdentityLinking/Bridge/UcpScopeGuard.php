<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Convenience guard around `UcpAccessTokenAuthenticator::ATTR_SCOPES`.
 *
 * UCP identity-linking.md mandates that "user-authenticated" operations
 * (cart:manage, order:read, order:manage) verify the access token granted
 * that scope. The bearer token is parsed upstream by
 * {@see UcpAccessTokenAuthenticator}; controllers call {@see require()} to
 * enforce a specific scope per operation.
 *
 * Anonymous-agent requests (no Authorization header) are allowed through:
 * those operations either don't require user context (catalog, cart create
 * without buyer linkage) or fall back to guest-customer provisioning at
 * complete time.
 *
 * @internal
 */
#[Package('framework')]
class UcpScopeGuard
{
    /**
     * Require that the current request was authorised with the given scope.
     * Anonymous (unauthenticated) requests pass through — call
     * {@see requireAuthenticated()} if user identity is mandatory.
     */
    public function require(Request $request, string $scope): void
    {
        $scopes = $request->attributes->get(UcpAccessTokenAuthenticator::ATTR_SCOPES);
        if (!\is_array($scopes)) {
            // Not bearer-authenticated → not subject to scope check.
            return;
        }

        if (!\in_array($scope, $scopes, true)) {
            throw UcpException::scopeRequired($scope, implode(' ', $scopes));
        }
    }

    /**
     * Require both authentication AND a specific scope.
     */
    public function requireAuthenticated(Request $request, string $scope): void
    {
        $userId = $request->attributes->get(UcpAccessTokenAuthenticator::ATTR_USER_ID);
        if (!\is_string($userId) || $userId === '') {
            throw UcpException::scopeRequired($scope, '(no bearer token)');
        }

        $this->require($request, $scope);
    }
}
