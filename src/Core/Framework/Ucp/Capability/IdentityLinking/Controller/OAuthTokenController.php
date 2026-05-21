<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Auth\ClientAuthenticator;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server\UcpAuthorizationServerFactory;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * OAuth 2.0 Token endpoint per Sales Channel — delegates entirely to the
 * League AuthorizationServer, which handles:
 *   - authorization_code grant with PKCE verification
 *   - refresh_token grant with rotation
 *   - client authentication
 *   - access-token issuance (JWT, signed with the Sales Channel's active
 *     UCP key)
 *   - persistence via repositories
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class OAuthTokenController
{
    public function __construct(
        private readonly SalesChannelDomainResolver $domainResolver,
        private readonly UcpConfigProvider $configProvider,
        private readonly UcpAuthorizationServerFactory $serverFactory,
        private readonly ClientAuthenticator $clientAuthenticator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/ucp/v1/oauth/token',
        name: 'ucp.identity.oauth.token',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function token(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return new Response('No matching sales channel domain', Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive() || !$config->isCapabilityEnabled('dev.ucp.common.identity_linking')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        // Pre-flight: validate `private_key_jwt` / `tls_client_auth` BEFORE
        // handing off to League — League v8 only understands client_secret_*.
        // If a non-secret method authenticates successfully, we synthesise a
        // public-client request so League skips secret validation.
        $tokenEndpoint = rtrim($domain->getUrl(), '/') . '/ucp/v1/oauth/token';
        try {
            $authClientId = $this->clientAuthenticator->authenticate(
                $request,
                $domain->getSalesChannelId(),
                $tokenEndpoint,
                $context
            );
        } catch (\InvalidArgumentException $e) {
            $this->logger->info('UCP OAuth client authentication failed', ['error' => $e->getMessage()]);

            return new JsonResponse([
                'error' => 'invalid_client',
                'error_description' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($authClientId !== null) {
            // Strip secret so League treats the call as `none` auth — our
            // ClientAuthenticator has already proven the client's identity.
            $request->request->set('client_secret', null);
            $request->request->remove('client_assertion');
            $request->request->remove('client_assertion_type');
        }

        $server = $this->serverFactory->create($domain->getSalesChannelId(), $context);
        $psrRequest = $this->toPsr($request);
        $psrResponse = $this->newPsrResponse();

        try {
            $psrResponse = $server->respondToAccessTokenRequest($psrRequest, $psrResponse);
        } catch (OAuthServerException $e) {
            $psrResponse = $e->generateHttpResponse($psrResponse);
        }

        return $this->fromPsr($psrResponse);
    }

    private function toPsr(Request $request): PsrServerRequestInterface
    {
        $psr17 = new Psr17Factory();
        $bridge = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);

        return $bridge->createRequest($request);
    }

    private function newPsrResponse(): PsrResponseInterface
    {
        return (new Psr17Factory())->createResponse();
    }

    private function fromPsr(PsrResponseInterface $psr): Response
    {
        $bridge = new HttpFoundationFactory();

        return $bridge->createResponse($psr);
    }
}
