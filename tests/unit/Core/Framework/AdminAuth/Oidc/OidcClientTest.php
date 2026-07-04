<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClient;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcDiscoveryService;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @internal
 */
#[CoversClass(OidcClient::class)]
class OidcClientTest extends TestCase
{
    public function testBuildAuthorizeUrlContainsAllOidcParameters(): void
    {
        $client = $this->createClient(new MockHttpClient());

        $url = $client->buildAuthorizeUrl(
            $this->provider(),
            'state-123',
            'nonce-456',
            'https://shop.example/api/_action/admin-auth/oidc/x/callback'
        );

        $parts = parse_url($url);
        static::assertIsArray($parts);
        static::assertSame('https', $parts['scheme'] ?? null);
        static::assertSame('idp.example', $parts['host'] ?? null);
        static::assertSame('/authorize', $parts['path'] ?? null);

        parse_str($parts['query'] ?? '', $query);
        static::assertSame([
            'response_type' => 'code',
            'client_id' => 'the-client',
            'redirect_uri' => 'https://shop.example/api/_action/admin-auth/oidc/x/callback',
            'scope' => 'openid profile email',
            'state' => 'state-123',
            'nonce' => 'nonce-456',
        ], $query);
    }

    public function testBuildAuthorizeUrlWithoutClientIdThrows(): void
    {
        $client = $this->createClient(new MockHttpClient());

        $this->expectExceptionObject(AdminAuthException::providerMisconfigured('Corporate SSO', 'missing client id'));

        $client->buildAuthorizeUrl($this->provider(clientId: ''), 'state', 'nonce', 'https://shop.example/callback');
    }

    public function testExchangeCodePostsTheCodeToTheTokenEndpointAndReturnsTheIdToken(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody): JsonMockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedBody = $options['body'] ?? null;

            return new JsonMockResponse(['id_token' => 'header.payload.signature', 'access_token' => 'unused']);
        });

        $idToken = $this->createClient($httpClient)->exchangeCode(
            $this->provider(),
            'auth-code-123',
            'https://shop.example/callback'
        );

        static::assertSame('header.payload.signature', $idToken);
        static::assertSame('POST', $capturedMethod);
        static::assertSame('https://idp.example/token', $capturedUrl);

        static::assertIsString($capturedBody);
        parse_str($capturedBody, $body);
        static::assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'auth-code-123',
            'redirect_uri' => 'https://shop.example/callback',
            'client_id' => 'the-client',
            'client_secret' => 'the-secret',
        ], $body);
    }

    public function testExchangeCodeWithoutIdTokenInTheResponseThrows(): void
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]));

        $this->expectExceptionObject(AdminAuthException::oidcTokenResponseInvalid());

        $this->createClient($httpClient)->exchangeCode($this->provider(), 'bad-code', 'https://shop.example/callback');
    }

    public function testExchangeCodeWithoutClientSecretThrows(): void
    {
        $this->expectExceptionObject(AdminAuthException::providerMisconfigured('Corporate SSO', 'missing client secret'));

        $this->createClient(new MockHttpClient())->exchangeCode(
            $this->provider(clientSecret: ''),
            'auth-code',
            'https://shop.example/callback'
        );
    }

    public function testGetJwksFetchesTheDocumentOnceAndCachesTheKeys(): void
    {
        $keys = [['kty' => 'RSA', 'kid' => 'key-1', 'n' => 'abc', 'e' => 'AQAB']];
        $httpClient = new MockHttpClient(new JsonMockResponse(['keys' => $keys]));
        $client = $this->createClient($httpClient);

        static::assertSame($keys, $client->getJwks($this->provider()));
        static::assertSame($keys, $client->getJwks($this->provider()));
        static::assertSame(1, $httpClient->getRequestsCount(), 'the JWKS document must be served from the cache on the second call');
    }

    public function testGetJwksWithoutAKeysArrayReturnsAnEmptyList(): void
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['not-keys' => true]));

        static::assertSame([], $this->createClient($httpClient)->getJwks($this->provider()));
    }

    private function createClient(MockHttpClient $httpClient): OidcClient
    {
        return new OidcClient(
            $httpClient,
            new ArrayAdapter(),
            new OidcDiscoveryService($httpClient, new ArrayAdapter())
        );
    }

    /**
     * A provider with explicit endpoints, so no discovery request is ever needed.
     */
    private function provider(string $clientId = 'the-client', string $clientSecret = 'the-secret'): AdminAuthProvider
    {
        return new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: $clientId,
            clientSecret: $clientSecret,
            issuer: 'https://idp.example',
            authorizationEndpoint: 'https://idp.example/authorize',
            tokenEndpoint: 'https://idp.example/token',
            jwksUri: 'https://idp.example/jwks',
        );
    }
}
