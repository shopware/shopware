<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\OidcVerifier;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\WebAuthnVerifier;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcDiscoveryService;
use Shopware\Core\Framework\AdminAuth\Oidc\StateService;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Login-method discovery and the OIDC login round-trip for the administration login screen.
 *
 * The OIDC login is completed server-side: the callback validates the CSRF state, drives an
 * internal `admin_primary` token request (method `oidc`) against the authorization server and
 * hands the issued token set to the admin SPA via the same `bearerAuth` cookie the admin's
 * `loginService.setBearerAuthentication` writes.
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly MethodSettingsService $methodSettings,
        private readonly OidcClient $oidcClient,
        private readonly OidcDiscoveryService $oidcDiscovery,
        private readonly StateService $stateService,
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly RouterInterface $router,
        private readonly ClockInterface $clock,
        private readonly WebAuthnService $webAuthnService,
        private readonly WebAuthnChallengeStore $webAuthnChallengeStore,
    ) {
    }

    /**
     * Lists the available primary login methods for the (unauthenticated) admin login screen.
     */
    #[Route(
        path: '/api/_action/admin-auth/methods',
        name: 'api.admin_auth.methods',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function methods(): JsonResponse
    {
        $this->ensureFeatureActive();

        $methods = [];

        // Built-in primary methods (password, passkey) the login screen renders natively.
        foreach (['password', WebAuthnVerifier::METHOD] as $builtIn) {
            if ($this->methodSettings->isPrimary($builtIn)) {
                $methods[] = [
                    'id' => $builtIn,
                    'type' => $builtIn,
                    'label' => null,
                    'startUrl' => null,
                ];
            }
        }

        foreach ($this->providerRegistry->all() as $provider) {
            $methods[] = [
                'id' => $provider->id,
                'type' => OidcVerifier::METHOD,
                'label' => $provider->label,
                'startUrl' => $this->router->generate(
                    'api.admin_auth.oidc.start',
                    ['providerId' => $provider->id],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ];
        }

        return new JsonResponse([
            'methods' => $methods,
            'managedByConfig' => $this->providerRegistry->isManagedByConfig(),
            'adminUiEnabled' => $this->providerRegistry->isAdminUiEnabled(),
        ]);
    }

    /**
     * Issues WebAuthn assertion options for a passwordless / second-factor passkey login. The
     * server-issued challenge is returned as a signed `challengeToken` the client must echo back in
     * the token request (see {@see WebAuthnChallengeStore}). Discoverable login uses an empty
     * allowCredentials list, so no user enumeration is possible through this endpoint.
     */
    #[Route(
        path: '/api/_action/admin-auth/webauthn/login-options',
        name: 'api.admin_auth.webauthn.login_options',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_POST]
    )]
    public function webauthnLoginOptions(): JsonResponse
    {
        $this->ensureFeatureActive();

        if (!$this->methodSettings->isEnabled(WebAuthnVerifier::METHOD)) {
            throw AdminAuthException::methodDisabled(WebAuthnVerifier::METHOD);
        }

        $options = $this->webAuthnService->createRequestOptions();
        $optionsJson = $this->webAuthnService->serializeOptions($options);

        return new JsonResponse([
            'options' => json_decode($optionsJson, true),
            'challengeToken' => $this->webAuthnChallengeStore->issue($optionsJson, WebAuthnChallengeStore::PURPOSE_LOGIN),
        ]);
    }

    /**
     * Begins an OIDC login: stores CSRF state + nonce in a signed cookie and redirects to the
     * provider's authorization endpoint.
     */
    #[Route(
        path: '/api/_action/admin-auth/oidc/{providerId}/start',
        name: 'api.admin_auth.oidc.start',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function oidcStart(string $providerId, Request $request): RedirectResponse
    {
        $this->ensureFeatureActive();

        $provider = $this->loadProvider($providerId);
        $stateData = $this->stateService->create($request, $provider->id);

        $url = $this->oidcClient->buildAuthorizeUrl(
            $provider,
            $stateData['state'],
            $stateData['nonce'],
            $this->callbackUrl($provider)
        );

        $response = new RedirectResponse($url);
        $response->headers->setCookie($stateData['cookie']);

        return $response;
    }

    /**
     * Handles the provider redirect: validates the state, drives the `admin_primary` grant with
     * `method=oidc` and returns the issued token to the admin SPA via a redirect with a
     * `bearerAuth` cookie. Failures redirect back to the login screen with an `ssoError` code.
     */
    #[Route(
        path: '/api/_action/admin-auth/oidc/{providerId}/callback',
        name: 'api.admin_auth.oidc.callback',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function oidcCallback(string $providerId, Request $request): Response
    {
        $this->ensureFeatureActive();

        try {
            $stored = $this->stateService->consume($request, $request->query->get('state'));
        } catch (AdminAuthException) {
            return $this->loginErrorRedirect('invalid-state');
        }

        if (!hash_equals($stored['providerId'], $providerId)) {
            return $this->loginErrorRedirect('invalid-state');
        }

        // Feed the parameters the OidcVerifier expects into an internal OAuth token request.
        $request->request->set('grant_type', AdminPrimaryGrant::TYPE);
        $request->request->set('method', OidcVerifier::METHOD);
        $request->request->set('client_id', 'administration');
        $request->request->set('providerId', $providerId);
        $request->request->set('code', (string) $request->query->get('code'));
        $request->request->set('nonce', $stored['nonce']);

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
        } catch (\Throwable) {
            return $this->loginErrorRedirect('login-failed');
        }

        return $this->tokenRedirect($response->getBody()->__toString());
    }

    /**
     * Auto-discovers an OIDC provider's endpoints from its discovery document, so an admin only
     * has to supply the issuer URL in the provider form.
     */
    #[Route(
        path: '/api/_action/admin-auth/oidc/discover',
        name: 'api.admin_auth.oidc.discover',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['admin_auth_provider:update']],
        methods: [Request::METHOD_POST]
    )]
    public function discoverOidc(Request $request): JsonResponse
    {
        $this->ensureFeatureActive();

        $issuer = (string) ($request->request->get('discoveryUrl') ?? $request->request->get('issuer'));

        try {
            $config = $this->oidcDiscovery->discover($issuer);
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($config);
    }

    private function ensureFeatureActive(): void
    {
        if (!Feature::isActive('ADMIN_AUTH')) {
            throw AdminAuthException::featureNotActive();
        }
    }

    private function loadProvider(string $providerId): AdminAuthProvider
    {
        $provider = $this->providerRegistry->byId($providerId);

        if ($provider === null || !$provider->active) {
            throw AdminAuthException::providerNotFound($providerId);
        }

        return $provider;
    }

    private function callbackUrl(AdminAuthProvider $provider): string
    {
        return $this->router->generate(
            'api.admin_auth.oidc.callback',
            ['providerId' => $provider->id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function tokenRedirect(string $tokenJson): RedirectResponse
    {
        /** @var array{access_token?: string, refresh_token?: string, expires_in?: int} $data */
        $data = json_decode($tokenJson, true) ?: [];

        if (!isset($data['access_token'])) {
            return $this->loginErrorRedirect('login-failed');
        }

        if (!isset($data['refresh_token'], $data['expires_in'])) {
            // The MFA policy required a second factor, so the primary grant issued a powerless
            // pending token without a refresh token. The redirect flow has no safe way to hand
            // that pending token to the SPA, so - like the FroshAdminAuth plugin - the user is
            // sent back to the login screen and has to complete an in-app login flow instead.
            return $this->loginErrorRedirect('mfa-required');
        }

        $adminUrl = $this->adminUrl();
        $expiresAt = $this->clock->now()->getTimestamp() + (int) $data['expires_in'];

        // Mirrors what the admin's loginService.setBearerAuthentication() writes: a `bearerAuth`
        // cookie on the admin path with `expiry` in milliseconds, readable by JS (not httpOnly).
        $cookieData = [
            'access' => $data['access_token'],
            'refresh' => $data['refresh_token'],
            'expiry' => $expiresAt * 1000,
        ];

        $response = new RedirectResponse($adminUrl . '#/');
        $response->headers->setCookie(new Cookie(
            'bearerAuth',
            json_encode($cookieData, \JSON_THROW_ON_ERROR),
            $expiresAt,
            parse_url($adminUrl, \PHP_URL_PATH) ?: '/',
            httpOnly: false,
            sameSite: Cookie::SAMESITE_STRICT
        ));
        $response->headers->setCookie($this->stateService->clearCookie());

        return $response;
    }

    private function loginErrorRedirect(string $code): RedirectResponse
    {
        $response = new RedirectResponse(rtrim($this->adminUrl(), '/') . '/#/login?ssoError=' . $code);
        $response->headers->setCookie($this->stateService->clearCookie());

        return $response;
    }

    private function adminUrl(): string
    {
        return $this->router->generate('administration.index', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
