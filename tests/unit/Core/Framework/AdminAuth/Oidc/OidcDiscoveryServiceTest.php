<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcDiscoveryService;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(OidcDiscoveryService::class)]
class OidcDiscoveryServiceTest extends TestCase
{
    private const WELL_KNOWN_URL = 'https://idp.example/.well-known/openid-configuration';

    public function testDiscoverFetchesTheWellKnownDocumentOfTheIssuer(): void
    {
        $requestedUrl = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse($this->discoveryDocument());
        });

        $config = $this->createService($client)->discover('https://idp.example/');

        static::assertSame(self::WELL_KNOWN_URL, $requestedUrl);
        static::assertSame([
            'issuer' => 'https://idp.example',
            'authorizationEndpoint' => 'https://idp.example/authorize',
            'tokenEndpoint' => 'https://idp.example/token',
            'jwksUri' => 'https://idp.example/jwks',
            // 'profile' is not supported by the provider, so it must not be requested.
            'scopes' => ['openid', 'email'],
        ], $config);
    }

    public function testDiscoverDoesNotDoubleTheWellKnownPath(): void
    {
        $requestedUrl = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): JsonMockResponse {
            $requestedUrl = $url;

            return new JsonMockResponse($this->discoveryDocument());
        });

        $this->createService($client)->discover(self::WELL_KNOWN_URL);

        static::assertSame(self::WELL_KNOWN_URL, $requestedUrl);
    }

    public function testDiscoverFallsBackToTheDefaultScopesWithoutASupportedList(): void
    {
        $document = $this->discoveryDocument();
        unset($document['scopes_supported'], $document['issuer']);

        $client = new MockHttpClient(new JsonMockResponse($document));

        $config = $this->createService($client)->discover('https://idp.example');

        static::assertSame(['openid', 'profile', 'email'], $config['scopes']);
        static::assertSame('https://idp.example', $config['issuer'], 'a missing issuer must fall back to the input URL');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIssuerProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'missing scheme' => ['idp.example'];
        yield 'unsupported scheme' => ['ftp://idp.example'];
    }

    #[DataProvider('invalidIssuerProvider')]
    public function testDiscoverRejectsNonHttpUrls(string $issuer): void
    {
        $client = new MockHttpClient();

        $this->expectExceptionObject(AdminAuthException::oidcDiscoveryFailed($issuer));

        $this->createService($client)->discover($issuer);
    }

    public function testDiscoverFailsWhenTheDocumentLacksRequiredEndpoints(): void
    {
        $document = $this->discoveryDocument();
        unset($document['token_endpoint']);

        $client = new MockHttpClient(new JsonMockResponse($document));

        $this->expectExceptionObject(AdminAuthException::oidcDiscoveryFailed(self::WELL_KNOWN_URL));

        $this->createService($client)->discover('https://idp.example');
    }

    public function testDiscoverWrapsUnreadableDocumentsIntoADomainException(): void
    {
        $client = new MockHttpClient(new MockResponse('<html>this is not JSON</html>', ['http_code' => 500]));

        $this->expectExceptionObject(AdminAuthException::oidcDiscoveryFailed(self::WELL_KNOWN_URL));

        $this->createService($client)->discover('https://idp.example');
    }

    public function testResolveEndpointsPrefersTheExplicitConfiguration(): void
    {
        $client = new MockHttpClient();
        $provider = $this->provider(
            issuer: 'https://explicit.example',
            authorizationEndpoint: 'https://explicit.example/authorize',
            tokenEndpoint: 'https://explicit.example/token',
            jwksUri: 'https://explicit.example/jwks',
            discoveryUrl: 'https://idp.example',
        );

        $endpoints = $this->createService($client)->resolveEndpoints($provider);

        static::assertSame([
            'issuer' => 'https://explicit.example',
            'authorizationEndpoint' => 'https://explicit.example/authorize',
            'tokenEndpoint' => 'https://explicit.example/token',
            'jwksUri' => 'https://explicit.example/jwks',
        ], $endpoints);
        static::assertSame(0, $client->getRequestsCount(), 'a fully configured provider must not trigger discovery');
    }

    public function testResolveEndpointsFillsMissingEndpointsFromTheCachedDiscoveryDocument(): void
    {
        $client = new MockHttpClient(new JsonMockResponse($this->discoveryDocument()));
        $service = $this->createService($client);
        $provider = $this->provider(
            authorizationEndpoint: 'https://explicit.example/authorize',
            discoveryUrl: 'https://idp.example',
        );

        $expected = [
            'issuer' => 'https://idp.example',
            // The explicitly configured endpoint wins over the discovered one.
            'authorizationEndpoint' => 'https://explicit.example/authorize',
            'tokenEndpoint' => 'https://idp.example/token',
            'jwksUri' => 'https://idp.example/jwks',
        ];

        static::assertSame($expected, $service->resolveEndpoints($provider));
        static::assertSame($expected, $service->resolveEndpoints($provider));
        static::assertSame(1, $client->getRequestsCount(), 'the discovery document must be cached after the first fetch');
    }

    public function testResolveEndpointsWithoutEndpointsOrDiscoveryUrlThrows(): void
    {
        $provider = $this->provider();

        $this->expectExceptionObject(AdminAuthException::providerMisconfigured(
            'Corporate SSO',
            'configure a discovery_url or the explicit authorization/token/JWKS endpoints'
        ));

        $this->createService(new MockHttpClient())->resolveEndpoints($provider);
    }

    private function createService(MockHttpClient $client): OidcDiscoveryService
    {
        return new OidcDiscoveryService($client, new ArrayAdapter());
    }

    /**
     * @return array<string, mixed>
     */
    private function discoveryDocument(): array
    {
        return [
            'issuer' => 'https://idp.example',
            'authorization_endpoint' => 'https://idp.example/authorize',
            'token_endpoint' => 'https://idp.example/token',
            'jwks_uri' => 'https://idp.example/jwks',
            'scopes_supported' => ['openid', 'email', 'offline_access'],
        ];
    }

    private function provider(
        ?string $issuer = null,
        ?string $authorizationEndpoint = null,
        ?string $tokenEndpoint = null,
        ?string $jwksUri = null,
        ?string $discoveryUrl = null,
    ): AdminAuthProvider {
        return new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            discoveryUrl: $discoveryUrl,
            issuer: $issuer,
            authorizationEndpoint: $authorizationEndpoint,
            tokenEndpoint: $tokenEndpoint,
            jwksUri: $jwksUri,
        );
    }
}
