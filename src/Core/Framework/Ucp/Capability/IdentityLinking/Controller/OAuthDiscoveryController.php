<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Scope\UcpScopeRegistry;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * RFC 8414 OAuth 2.0 Authorization Server Metadata Discovery endpoint —
 * `/ucp/v1/.well-known/oauth-authorization-server` per Sales Channel domain.
 *
 * Platforms discover the OAuth endpoints, supported scopes, response types
 * and PKCE methods supported by this Sales Channel.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class OAuthDiscoveryController
{
    public function __construct(
        private readonly SalesChannelDomainResolver $domainResolver,
        private readonly UcpConfigProvider $configProvider,
        private readonly UcpScopeRegistry $scopeRegistry,
    ) {
    }

    /**
     * Per RFC 8414 §3 the AS metadata MUST be served from the canonical
     * `/.well-known/oauth-authorization-server` path on the issuer host.
     * We also keep the legacy `/ucp/v1/.well-known/oauth-authorization-server`
     * route as an alias so the UCP-prefixed URL keeps working — both routes
     * dispatch to {@see discoverImpl()}.
     */
    #[Route(
        path: '/.well-known/oauth-authorization-server',
        name: 'ucp.identity.oauth.discovery.well_known',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function discover(Request $request): Response
    {
        return $this->discoverImpl($request);
    }

    #[Route(
        path: '/ucp/v1/.well-known/oauth-authorization-server',
        name: 'ucp.identity.oauth.discovery',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function discoverLegacy(Request $request): Response
    {
        return $this->discoverImpl($request);
    }

    private function discoverImpl(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive()) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $baseUrl = rtrim($domain->getUrl(), '/');

        $metadata = [
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/ucp/v1/oauth/authorize',
            'token_endpoint' => $baseUrl . '/ucp/v1/oauth/token',
            // signing_keys live inside the UCP profile (jwks subset of /.well-known/ucp)
            'jwks_uri' => $baseUrl . '/.well-known/ucp',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            // UCP identity-linking.md §"Client Authentication":
            //   - public clients: `none` (must use PKCE)
            //   - confidential clients: `client_secret_post` OR `private_key_jwt`
            //     OR `tls_client_auth`. We advertise all three; runtime
            //     selection is per registered client.
            'token_endpoint_auth_methods_supported' => [
                'none',
                'client_secret_post',
                'private_key_jwt',
                'tls_client_auth',
            ],
            'token_endpoint_auth_signing_alg_values_supported' => ['ES256', 'ES384'],
            'code_challenge_methods_supported' => ['S256'],
            // UCP mandates PKCE for both public AND confidential clients.
            'require_pushed_authorization_requests' => false,
            // RFC 9207 — return `iss` on the authorization response so the
            // client cannot be tricked into accepting a code from the wrong
            // server. UCP identity-linking.md mandates this MUST be true.
            // The actual `iss` query parameter is appended to the redirect
            // URI by `OAuthAuthorizeController::issueRedirect()`.
            'authorization_response_iss_parameter_supported' => true,
            'scopes_supported' => $this->scopeRegistry->all(),
            'subject_types_supported' => ['public'],
            // Spec §"Identity Optional": the AS surfaces a per-scope hint that
            // the resource server can use to decide whether identity is
            // optional or required for a given operation.
            'ucp_identity_optional_scopes' => $this->scopeRegistry->identityOptionalScopes(),
        ];

        $response = new JsonResponse($metadata);
        $response->headers->set('Cache-Control', 'public, max-age=300');

        return $response;
    }
}
