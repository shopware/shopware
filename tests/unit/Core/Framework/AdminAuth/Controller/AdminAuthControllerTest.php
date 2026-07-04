<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Controller;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Controller\AdminAuthController;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcDiscoveryService;
use Shopware\Core\Framework\AdminAuth\Oidc\StateService;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AdminAuthController::class)]
class AdminAuthControllerTest extends TestCase
{
    private const APP_SECRET = 'test-app-secret';
    private const NOW = '2026-01-01 12:00:00';
    private const PROVIDER_ID = 'a5b4885a89694a4c8e28e00b48b09dcc';

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testEveryRouteRequiresTheFeatureFlag(): void
    {
        $controller = $this->createController();
        $request = new Request();

        $routes = [
            static fn () => $controller->methods(),
            static fn () => $controller->webauthnLoginOptions(),
            static fn () => $controller->oidcStart(self::PROVIDER_ID, $request),
            static fn () => $controller->oidcCallback(self::PROVIDER_ID, $request),
            static fn () => $controller->discoverOidc($request),
        ];

        foreach ($routes as $index => $route) {
            try {
                $route();
                static::fail(\sprintf('route #%d must be gated by the ADMIN_AUTH feature flag', $index));
            } catch (AdminAuthException $exception) {
                static::assertSame(AdminAuthException::FEATURE_NOT_ACTIVE, $exception->getErrorCode());
            }
        }
    }

    public function testMethodsListsBuiltInsAndOidcProviders(): void
    {
        $controller = $this->createController(
            registry: $this->registry($this->provider(), managedByConfig: true, adminUiEnabled: false),
        );

        $response = $controller->methods();

        static::assertSame([
            'methods' => [
                ['id' => 'password', 'type' => 'password', 'label' => null, 'startUrl' => null],
                ['id' => 'webauthn', 'type' => 'webauthn', 'label' => null, 'startUrl' => null],
                [
                    'id' => self::PROVIDER_ID,
                    'type' => 'oidc',
                    'label' => 'Corporate SSO',
                    'startUrl' => 'http://localhost/api/_action/admin-auth/oidc/' . self::PROVIDER_ID . '/start',
                ],
            ],
            'managedByConfig' => true,
            'adminUiEnabled' => false,
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testMethodsOmitsDisabledPasswordLogin(): void
    {
        $controller = $this->createController(
            methodSettings: new MethodSettingsService(new StaticSystemConfigService(), passwordLoginEnabled: false),
        );

        $response = $controller->methods();

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertSame(
            [['id' => 'webauthn', 'type' => 'webauthn', 'label' => null, 'startUrl' => null]],
            $data['methods'],
            'a password login disabled via YAML must not be offered'
        );
    }

    public function testWebauthnLoginOptionsRejectsADisabledMethod(): void
    {
        $controller = $this->createController(
            methodSettings: new MethodSettingsService(new StaticSystemConfigService(), ['webauthn' => false]),
        );

        $this->expectExceptionObject(AdminAuthException::methodDisabled('webauthn'));

        $controller->webauthnLoginOptions();
    }

    public function testWebauthnLoginOptionsIssuesAConsumableChallengeToken(): void
    {
        $response = $this->createController()->webauthnLoginOptions();

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsArray($data['options']);
        static::assertSame('localhost', $data['options']['rpId']);
        static::assertSame(
            [],
            $data['options']['allowCredentials'] ?? [],
            'discoverable login must not enumerate credentials'
        );

        static::assertIsString($data['challengeToken']);
        $store = new WebAuthnChallengeStore(self::APP_SECRET, new MockClock(self::NOW));
        $optionsJson = $store->consume($data['challengeToken'], WebAuthnChallengeStore::PURPOSE_LOGIN);
        static::assertNotNull($optionsJson);
        static::assertSame($data['options'], json_decode($optionsJson, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testOidcStartRedirectsToTheProviderAndStoresTheState(): void
    {
        $provider = $this->provider();

        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->expects($this->once())
            ->method('buildAuthorizeUrl')
            ->willReturnCallback(static function (AdminAuthProvider $usedProvider, string $state, string $nonce, string $redirectUri) use ($provider): string {
                static::assertSame($provider, $usedProvider);
                static::assertNotSame('', $state);
                static::assertNotSame('', $nonce);
                static::assertNotSame($state, $nonce);
                static::assertSame('http://localhost/api/_action/admin-auth/oidc/' . self::PROVIDER_ID . '/callback', $redirectUri);

                return 'https://idp.example/authorize?state=' . $state;
            });

        $response = $this->createController(registry: $this->registry($provider), oidcClient: $oidcClient)
            ->oidcStart(self::PROVIDER_ID, Request::create('http://localhost/api/_action/admin-auth/oidc/' . self::PROVIDER_ID . '/start'));

        static::assertStringStartsWith('https://idp.example/authorize?state=', $response->getTargetUrl());

        $stateCookie = $this->cookieByName($response, StateService::COOKIE_NAME);
        static::assertNotNull($stateCookie, 'the CSRF state must travel in a signed cookie');
        static::assertSame('/api/_action/admin-auth', $stateCookie->getPath());
        static::assertTrue($stateCookie->isHttpOnly());
    }

    public function testOidcStartRejectsAnUnknownProvider(): void
    {
        $controller = $this->createController(registry: $this->registry());

        $this->expectExceptionObject(AdminAuthException::providerNotFound(self::PROVIDER_ID));

        $controller->oidcStart(self::PROVIDER_ID, new Request());
    }

    public function testOidcStartRejectsAnInactiveProvider(): void
    {
        $controller = $this->createController(registry: $this->registry($this->provider(active: false)));

        $this->expectExceptionObject(AdminAuthException::providerNotFound(self::PROVIDER_ID));

        $controller->oidcStart(self::PROVIDER_ID, new Request());
    }

    public function testOidcCallbackWithoutAValidStateRedirectsToTheLoginScreen(): void
    {
        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->expects($this->never())->method('respondToAccessTokenRequest');

        $response = $this->createController(authorizationServer: $authorizationServer)
            ->oidcCallback(self::PROVIDER_ID, Request::create('http://localhost/callback?state=whatever'));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('http://localhost/admin/#/login?ssoError=invalid-state', $response->getTargetUrl());
    }

    public function testOidcCallbackRejectsAStateIssuedForAnotherProvider(): void
    {
        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->expects($this->never())->method('respondToAccessTokenRequest');

        $request = $this->callbackRequest(stateProviderId: 'ffffffffffffffffffffffffffffffff');

        $response = $this->createController(authorizationServer: $authorizationServer)
            ->oidcCallback(self::PROVIDER_ID, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('http://localhost/admin/#/login?ssoError=invalid-state', $response->getTargetUrl());
    }

    public function testOidcCallbackDrivesThePrimaryGrantAndHandsTheTokenToTheSpa(): void
    {
        $capturedRequest = null;
        $tokenJson = json_encode([
            'access_token' => 'the-access-token',
            'refresh_token' => 'the-refresh-token',
            'expires_in' => 600,
        ], \JSON_THROW_ON_ERROR);

        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturnCallback(static function (ServerRequestInterface $psrRequest) use (&$capturedRequest, $tokenJson): Psr7Response {
                $capturedRequest = $psrRequest;

                return new Psr7Response(200, [], $tokenJson);
            });

        [$request, $nonce] = $this->callbackRequestWithNonce();

        $response = $this->createController(authorizationServer: $authorizationServer)
            ->oidcCallback(self::PROVIDER_ID, $request);

        static::assertInstanceOf(ServerRequestInterface::class, $capturedRequest);
        static::assertSame([
            'grant_type' => 'admin_primary',
            'method' => 'oidc',
            'client_id' => 'administration',
            'providerId' => self::PROVIDER_ID,
            'code' => 'auth-code-123',
            'nonce' => $nonce,
        ], $capturedRequest->getParsedBody());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('http://localhost/admin#/', $response->getTargetUrl());

        $bearerCookie = $this->cookieByName($response, 'bearerAuth');
        static::assertNotNull($bearerCookie, 'the token must be handed over via the bearerAuth cookie');
        static::assertSame('/admin', $bearerCookie->getPath());
        static::assertFalse($bearerCookie->isHttpOnly(), 'the admin SPA must be able to read the cookie');
        static::assertSame(Cookie::SAMESITE_STRICT, $bearerCookie->getSameSite());

        $expiresAt = (new \DateTimeImmutable(self::NOW))->getTimestamp() + 600;
        static::assertSame($expiresAt, $bearerCookie->getExpiresTime());
        static::assertSame([
            'access' => 'the-access-token',
            'refresh' => 'the-refresh-token',
            'expiry' => $expiresAt * 1000,
        ], json_decode((string) $bearerCookie->getValue(), true, 512, \JSON_THROW_ON_ERROR));

        $stateCookie = $this->cookieByName($response, StateService::COOKIE_NAME);
        static::assertNotNull($stateCookie, 'the state cookie must be cleared, the state is single-use');
        static::assertNull($stateCookie->getValue());
    }

    public function testOidcCallbackWithAPendingMfaTokenSendsTheUserBackToTheLoginScreen(): void
    {
        // A pending token (MFA required) has no refresh token and no expiry.
        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn(new Psr7Response(200, [], (string) json_encode(['access_token' => 'pending-token'])));

        [$request] = $this->callbackRequestWithNonce();

        $response = $this->createController(authorizationServer: $authorizationServer)
            ->oidcCallback(self::PROVIDER_ID, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('http://localhost/admin/#/login?ssoError=mfa-required', $response->getTargetUrl());
    }

    public function testOidcCallbackRedirectsToTheLoginScreenWhenTheGrantFails(): void
    {
        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException(OAuthServerException::invalidGrant());

        [$request] = $this->callbackRequestWithNonce();

        $response = $this->createController(authorizationServer: $authorizationServer)
            ->oidcCallback(self::PROVIDER_ID, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('http://localhost/admin/#/login?ssoError=login-failed', $response->getTargetUrl());
    }

    public function testDiscoverOidcReturnsTheDiscoveredConfiguration(): void
    {
        $config = [
            'issuer' => 'https://idp.example',
            'authorizationEndpoint' => 'https://idp.example/authorize',
            'tokenEndpoint' => 'https://idp.example/token',
            'jwksUri' => 'https://idp.example/jwks',
            'scopes' => ['openid', 'email'],
        ];

        $discovery = $this->createMock(OidcDiscoveryService::class);
        $discovery->expects($this->once())
            ->method('discover')
            ->with('https://idp.example')
            ->willReturn($config);

        $response = $this->createController(discovery: $discovery)
            ->discoverOidc(new Request(request: ['discoveryUrl' => 'https://idp.example']));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($config, json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDiscoverOidcTurnsFailuresIntoABadRequest(): void
    {
        $discovery = static::createStub(OidcDiscoveryService::class);
        $discovery->method('discover')
            ->willThrowException(AdminAuthException::oidcDiscoveryFailed('https://broken.example'));

        $response = $this->createController(discovery: $discovery)
            ->discoverOidc(new Request(request: ['issuer' => 'https://broken.example']));

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsString($data['error']);
        static::assertStringContainsString('https://broken.example', $data['error']);
    }

    private function createController(
        ?ProviderRegistry $registry = null,
        ?MethodSettingsService $methodSettings = null,
        ?OidcClient $oidcClient = null,
        ?OidcDiscoveryService $discovery = null,
        ?AuthorizationServer $authorizationServer = null,
    ): AdminAuthController {
        $clock = new MockClock(self::NOW);
        $psr17Factory = new Psr17Factory();

        return new AdminAuthController(
            $registry ?? $this->registry(),
            $methodSettings ?? new MethodSettingsService(new StaticSystemConfigService()),
            $oidcClient ?? static::createStub(OidcClient::class),
            $discovery ?? static::createStub(OidcDiscoveryService::class),
            $this->stateService(),
            $authorizationServer ?? static::createStub(AuthorizationServer::class),
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
            $this->router(),
            $clock,
            new WebAuthnService('localhost', 'Shopware Admin', ['http://localhost']),
            new WebAuthnChallengeStore(self::APP_SECRET, $clock),
        );
    }

    /**
     * Interchangeable with the controller's own instance: same secret, same frozen clock.
     */
    private function stateService(): StateService
    {
        return new StateService(self::APP_SECRET, new MockClock(self::NOW));
    }

    private function registry(
        ?AdminAuthProvider $provider = null,
        bool $managedByConfig = false,
        bool $adminUiEnabled = true,
    ): ProviderRegistry {
        $registry = static::createStub(ProviderRegistry::class);
        $registry->method('all')->willReturn($provider !== null && $provider->active ? [$provider] : []);
        $registry->method('byId')->willReturnCallback(
            static fn (string $id): ?AdminAuthProvider => $provider !== null && strtolower($id) === $provider->id ? $provider : null
        );
        $registry->method('isManagedByConfig')->willReturn($managedByConfig);
        $registry->method('isAdminUiEnabled')->willReturn($adminUiEnabled);

        return $registry;
    }

    private function router(): RouterInterface
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => match ($name) {
                'administration.index' => 'http://localhost/admin',
                'api.admin_auth.oidc.start' => \sprintf('http://localhost/api/_action/admin-auth/oidc/%s/start', $parameters['providerId']),
                'api.admin_auth.oidc.callback' => \sprintf('http://localhost/api/_action/admin-auth/oidc/%s/callback', $parameters['providerId']),
                default => throw new \RuntimeException('Unexpected route ' . $name),
            }
        );

        return $router;
    }

    private function provider(bool $active = true): AdminAuthProvider
    {
        return new AdminAuthProvider(
            id: self::PROVIDER_ID,
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            active: $active,
        );
    }

    /**
     * A callback request carrying a valid, signed state cookie.
     */
    private function callbackRequest(string $stateProviderId = self::PROVIDER_ID): Request
    {
        $stateData = $this->stateService()->create(Request::create('http://localhost/'), $stateProviderId);

        $request = Request::create(
            'http://localhost/api/_action/admin-auth/oidc/' . self::PROVIDER_ID . '/callback',
            Request::METHOD_GET,
            ['state' => $stateData['state'], 'code' => 'auth-code-123']
        );
        $request->cookies->set(StateService::COOKIE_NAME, (string) $stateData['cookie']->getValue());
        $request->attributes->set('_stored_nonce', $stateData['nonce']);

        return $request;
    }

    /**
     * @return array{0: Request, 1: string} the request and the nonce stored in its state cookie
     */
    private function callbackRequestWithNonce(): array
    {
        $request = $this->callbackRequest();
        $nonce = $request->attributes->get('_stored_nonce');
        static::assertIsString($nonce);

        return [$request, $nonce];
    }

    private function cookieByName(Response $response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
