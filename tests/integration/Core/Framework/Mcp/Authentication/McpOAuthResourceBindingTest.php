<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Authentication;

use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\OAuthResource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorization code flow with an RFC 8707 resource, as MCP clients run it against `/api/_mcp`.
 *
 * @internal
 */
#[Package('framework')]
class McpOAuthResourceBindingTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CLIENT_ID = 'shopware-cli';
    private const REDIRECT_URI = 'http://127.0.0.1:54321/callback';
    private const RESOURCE = 'http://localhost/api/_mcp';
    private const ISSUER = 'http://localhost/api';

    private string $codeVerifier;

    protected function setUp(): void
    {
        $this->codeVerifier = bin2hex(random_bytes(32));
    }

    public function testAuthorizeForwardsTheResourceToTheConsentPage(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', $this->authorizationQuery(self::RESOURCE));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url(substr($location, (int) strpos($location, '#') + 1), \PHP_URL_QUERY), $forwarded);
        static::assertSame(self::RESOURCE, $forwarded['resource']);
    }

    public function testAuthorizeRedirectsAForeignResourceBackAsInvalidTarget(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', $this->authorizationQuery('https://other.example/api/_mcp'));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $location = (string) $response->headers->get('Location');
        static::assertStringStartsWith(self::REDIRECT_URI . '?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $callback);
        static::assertSame(OAuthResource::ERROR_INVALID_TARGET, $callback['error']);
        static::assertSame('xyz', $callback['state']);
        static::assertSame(self::ISSUER, $callback['iss']);
    }

    public function testApprovalCannotBindAForeignResource(): void
    {
        $browser = $this->getBrowser();
        $payload = [...$this->authorizationQuery('http://localhost/api/_action/sync'), 'approved' => true];
        $browser->request('POST', '/api/oauth/authorize', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame((string) OAuthResource::ERROR_CODE_INVALID_TARGET, $content['errors'][0]['code']);
    }

    public function testAuthorizationResponsesCarryTheIssuer(): void
    {
        parse_str((string) parse_url($this->approve(null, approved: true), \PHP_URL_QUERY), $approved);
        static::assertSame(self::ISSUER, $approved['iss']);

        parse_str((string) parse_url($this->approve(null, approved: false), \PHP_URL_QUERY), $denied);
        static::assertSame('access_denied', $denied['error']);
        static::assertSame(self::ISSUER, $denied['iss']);
    }

    public function testMcpTokenIsAcceptedOnTheMcpEndpointOnly(): void
    {
        $tokens = $this->exchangeCode($this->authorize(self::RESOURCE), self::RESOURCE);
        static::assertArrayHasKey('access_token', $tokens, json_encode($tokens, \JSON_THROW_ON_ERROR));

        $jwt = $this->parse($tokens['access_token']);
        static::assertSame([self::CLIENT_ID, self::RESOURCE], $jwt->claims()->get('aud'));

        static::assertSame(Response::HTTP_OK, $this->initializeMcp($tokens['access_token'])->getStatusCode());

        $browser = $this->bearerBrowser($tokens['access_token']);
        $browser->request('GET', '/api/_info/me');
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('MCP endpoint', (string) $response->getContent());

        // an MCP token can never approve another client
        $payload = [...$this->authorizationQuery(null), 'approved' => true];
        $browser->request('POST', '/api/oauth/authorize', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));
        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testTokenWithoutResourceKeepsWorkingEverywhere(): void
    {
        $tokens = $this->exchangeCode($this->authorize(null), null);
        static::assertArrayHasKey('access_token', $tokens, json_encode($tokens, \JSON_THROW_ON_ERROR));
        static::assertSame([self::CLIENT_ID], $this->parse($tokens['access_token'])->claims()->get('aud'));

        $browser = $this->bearerBrowser($tokens['access_token']);
        $browser->request('GET', '/api/_info/me');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

        static::assertSame(Response::HTTP_OK, $this->initializeMcp($tokens['access_token'])->getStatusCode());

        $refreshed = $this->requestToken(['grant_type' => 'refresh_token', 'client_id' => self::CLIENT_ID, 'refresh_token' => $tokens['refresh_token']]);
        static::assertSame([self::CLIENT_ID], $this->parse($refreshed['access_token'])->claims()->get('aud'));
    }

    public function testRefreshKeepsTheResource(): void
    {
        $tokens = $this->exchangeCode($this->authorize(self::RESOURCE), self::RESOURCE);

        $refreshed = $this->requestToken(['grant_type' => 'refresh_token', 'client_id' => self::CLIENT_ID, 'refresh_token' => $tokens['refresh_token']]);
        static::assertArrayHasKey('access_token', $refreshed, json_encode($refreshed, \JSON_THROW_ON_ERROR));
        static::assertSame([self::CLIENT_ID, self::RESOURCE], $this->parse($refreshed['access_token'])->claims()->get('aud'));

        $browser = $this->bearerBrowser($refreshed['access_token']);
        $browser->request('GET', '/api/_info/me');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());

        $again = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $refreshed['refresh_token'],
            'resource' => self::RESOURCE,
        ]);
        static::assertSame([self::CLIENT_ID, self::RESOURCE], $this->parse($again['access_token'])->claims()->get('aud'));
    }

    public function testRefreshCannotChangeTheResource(): void
    {
        $plain = $this->exchangeCode($this->authorize(null), null);
        $widened = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $plain['refresh_token'],
            'resource' => self::RESOURCE,
        ]);
        $this->assertInvalidTarget($widened);
    }

    public function testTokenRequestMustNameTheResourceOfTheAuthorizationRequest(): void
    {
        $this->assertInvalidTarget($this->exchangeCode($this->authorize(self::RESOURCE), 'https://other.example/api/_mcp'));
        $this->assertInvalidTarget($this->exchangeCode($this->authorize(null), self::RESOURCE));
    }

    public function testTokenRequestWithoutResourceStillGetsTheGrantedResource(): void
    {
        $tokens = $this->exchangeCode($this->authorize(self::RESOURCE), null);

        static::assertSame([self::CLIENT_ID, self::RESOURCE], $this->parse($tokens['access_token'])->claims()->get('aud'));
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertInvalidTarget(array $response): void
    {
        static::assertArrayNotHasKey('access_token', $response);
        static::assertSame((string) OAuthResource::ERROR_CODE_INVALID_TARGET, $response['errors'][0]['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    private function authorizationQuery(?string $resource): array
    {
        $query = [
            'response_type' => 'code',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];

        if ($resource !== null) {
            $query['resource'] = $resource;
        }

        return $query;
    }

    private function authorize(?string $resource): string
    {
        parse_str((string) parse_url($this->approve($resource, approved: true), \PHP_URL_QUERY), $callback);
        static::assertIsString($callback['code'] ?? null);

        return $callback['code'];
    }

    private function approve(?string $resource, bool $approved): string
    {
        $browser = $this->getBrowser();
        $payload = [...$this->authorizationQuery($resource), 'approved' => $approved];
        $browser->request('POST', '/api/oauth/authorize', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($content['redirectUri'] ?? null);

        return $content['redirectUri'];
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code, ?string $resource): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $code,
            'code_verifier' => $this->codeVerifier,
        ];

        if ($resource !== null) {
            $payload['resource'] = $resource;
        }

        return $this->requestToken($payload);
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function requestToken(array $payload): array
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/oauth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));

        return json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function initializeMcp(string $accessToken): Response
    {
        $browser = $this->bearerBrowser($accessToken);
        $browser->request('POST', '/api/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'mcp-oauth-test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR));

        return $browser->getResponse();
    }

    private function bearerBrowser(string $accessToken): KernelBrowser
    {
        $browser = $this->getBrowser(false);
        $browser->setServerParameter('HTTP_Authorization', 'Bearer ' . $accessToken);

        return $browser;
    }

    private function parse(string $jwt): UnencryptedToken
    {
        static::assertNotSame('', $jwt);
        $token = static::getContainer()->get('shopware.jwt_config')->parser()->parse($jwt);
        static::assertInstanceOf(UnencryptedToken::class, $token);

        return $token;
    }
}
