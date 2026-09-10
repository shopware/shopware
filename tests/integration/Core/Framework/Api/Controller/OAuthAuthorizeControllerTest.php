<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class OAuthAuthorizeControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const CLIENT_ID = 'shopware-cli';
    private const REDIRECT_URI = 'http://127.0.0.1:54321/callback';

    private string $codeVerifier;

    protected function setUp(): void
    {
        $this->codeVerifier = bin2hex(random_bytes(32));
    }

    public function testAuthorizeRedirectsToAdministrationConsentPage(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', $this->authorizationQuery());

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $location = (string) $response->headers->get('Location');
        static::assertStringContainsString('#/oauth/authorize?', $location);

        parse_str((string) parse_url(substr($location, (int) strpos($location, '#') + 1), \PHP_URL_QUERY), $forwarded);
        static::assertEquals($this->authorizationQuery(), $forwarded);
    }

    public function testInfoDescribesTheClient(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', '/api/oauth/authorize/info', $this->authorizationQuery());

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame([
            'client' => ['id' => self::CLIENT_ID, 'name' => 'Shopware CLI'],
            'redirectUri' => self::REDIRECT_URI,
            'scopes' => ['write'],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testInfoRequiresAuthentication(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('GET', '/api/oauth/authorize/info', $this->authorizationQuery());

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testFullAuthorizationCodeFlow(): void
    {
        $approvingBrowser = $this->getBrowser();
        $approvingUserId = $this->getUserIdOfBrowser($approvingBrowser);
        $redirectUri = $this->approve(approved: true, browser: $approvingBrowser);

        static::assertStringStartsWith(self::REDIRECT_URI . '?', $redirectUri);
        parse_str((string) parse_url($redirectUri, \PHP_URL_QUERY), $callback);
        static::assertSame('xyz', $callback['state']);
        static::assertIsString($callback['code']);

        $tokens = $this->exchangeCode($callback['code'], $this->codeVerifier);
        static::assertArrayHasKey('access_token', $tokens, json_encode($tokens, \JSON_THROW_ON_ERROR));
        static::assertArrayHasKey('refresh_token', $tokens);

        $accessToken = static::getContainer()->get('shopware.jwt_config')->parser()->parse($tokens['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $accessToken);
        static::assertSame($approvingUserId, $accessToken->claims()->get('sub'));
        static::assertTrue($accessToken->isPermittedFor(self::CLIENT_ID));
        static::assertEqualsCanonicalizing(['write', 'admin'], array_values($accessToken->claims()->get('scopes')));

        $browser = $this->getBrowser(false);
        $browser->setServerParameter('HTTP_Authorization', 'Bearer ' . $tokens['access_token']);
        $browser->request('GET', '/api/_info/me');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $me = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($approvingUserId, $me['data']['id']);

        // the code is single use
        $reused = $this->exchangeCode($callback['code'], $this->codeVerifier);
        static::assertArrayNotHasKey('access_token', $reused);
        static::assertSame((string) OAuthServerException::invalidGrant()->getCode(), $reused['errors'][0]['code'] ?? null, json_encode($reused, \JSON_THROW_ON_ERROR));

        // refresh tokens rotate
        $refreshed = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $tokens['refresh_token'],
        ]);
        static::assertArrayHasKey('access_token', $refreshed, json_encode($refreshed, \JSON_THROW_ON_ERROR));
        static::assertArrayHasKey('refresh_token', $refreshed);
        static::assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);

        $reusedRefresh = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $tokens['refresh_token'],
        ]);
        static::assertArrayNotHasKey('access_token', $reusedRefresh);

        $revokedSuccessor = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $refreshed['refresh_token'],
        ]);
        static::assertArrayNotHasKey('access_token', $revokedSuccessor);
    }

    public function testLogoutRevokesPendingAuthorizationCode(): void
    {
        $browser = $this->getBrowser();
        $redirectUri = $this->approve(approved: true, browser: $browser);
        parse_str((string) parse_url($redirectUri, \PHP_URL_QUERY), $callback);
        static::assertIsString($callback['code']);

        $browser->request('POST', '/api/_action/user/logout');
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        $tokens = $this->exchangeCode($callback['code'], $this->codeVerifier);
        static::assertArrayNotHasKey('access_token', $tokens);
        static::assertSame(
            (string) OAuthServerException::invalidGrant()->getCode(),
            $tokens['errors'][0]['code'] ?? null,
            json_encode($tokens, \JSON_THROW_ON_ERROR)
        );
    }

    public function testTokensCarryPermissionsOfTheApprovingUser(): void
    {
        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['product:read']);
        $browser = $this->getBrowser(false);
        $user->authorizeBrowser($browser);

        $redirectUri = $this->approve(approved: true, browser: $browser);
        parse_str((string) parse_url($redirectUri, \PHP_URL_QUERY), $callback);
        static::assertIsString($callback['code']);

        $tokens = $this->exchangeCode($callback['code'], $this->codeVerifier);
        $accessToken = static::getContainer()->get('shopware.jwt_config')->parser()->parse($tokens['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $accessToken);
        static::assertSame($user->getUserId(), $accessToken->claims()->get('sub'));
        static::assertSame(['write'], array_values($accessToken->claims()->get('scopes')));

        $apiBrowser = $this->getBrowser(false);
        $apiBrowser->setServerParameter('HTTP_Authorization', 'Bearer ' . $tokens['access_token']);
        $apiBrowser->request('GET', '/api/product');
        static::assertSame(Response::HTTP_OK, $apiBrowser->getResponse()->getStatusCode());

        $apiBrowser->request('GET', '/api/tax');
        static::assertSame(Response::HTTP_FORBIDDEN, $apiBrowser->getResponse()->getStatusCode());
    }

    public function testDenyRedirectsWithAccessDenied(): void
    {
        $redirectUri = $this->approve(approved: false);

        static::assertStringStartsWith(self::REDIRECT_URI . '?', $redirectUri);
        parse_str((string) parse_url($redirectUri, \PHP_URL_QUERY), $callback);
        static::assertSame('access_denied', $callback['error']);
        static::assertSame('xyz', $callback['state']);
        static::assertArrayNotHasKey('code', $callback);
    }

    public function testWrongCodeVerifierIsRejected(): void
    {
        $redirectUri = $this->approve(approved: true);
        parse_str((string) parse_url($redirectUri, \PHP_URL_QUERY), $callback);
        static::assertIsString($callback['code']);

        $tokens = $this->exchangeCode($callback['code'], bin2hex(random_bytes(32)));

        static::assertArrayNotHasKey('access_token', $tokens);
        static::assertSame((string) OAuthServerException::invalidGrant()->getCode(), $tokens['errors'][0]['code'] ?? null, json_encode($tokens, \JSON_THROW_ON_ERROR));
    }

    public function testIntegrationTokenCannotApprove(): void
    {
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $payload = [...$this->authorizationQuery(), 'approved' => true];
        $browser->request('POST', '/api/oauth/authorize', $payload, [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('FRAMEWORK__API_EXPECTED_USER', $content['errors'][0]['code']);
    }

    public function testUnknownClientIsNotRedirected(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', [...$this->authorizationQuery(), 'client_id' => 'unknown']);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertFalse($response->headers->has('Location'));
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('FRAMEWORK__API_INVALID_ACCESS_KEY', $content['errors'][0]['code']);
    }

    public function testForeignRedirectUriIsNotRedirected(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', [...$this->authorizationQuery(), 'redirect_uri' => 'http://evil.example/callback']);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertFalse($response->headers->has('Location'));
    }

    public function testLocalhostIsNotALoopbackRedirectUri(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', [...$this->authorizationQuery(), 'redirect_uri' => 'http://localhost:54321/callback']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());
    }

    #[DataProvider('invalidRedirectEndpointProvider')]
    public function testInvalidRedirectReturnsAReadableJsonError(string $method, string $path, bool $authenticated, string $accept): void
    {
        $browser = $this->getBrowser($authenticated);
        $browser->followRedirects(false);
        $browser->request($method, $path, [
            ...$this->authorizationQuery(),
            'redirect_uri' => 'https://example.invalid/callback',
            'approved' => true,
        ], server: ['HTTP_ACCEPT' => $accept]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertFalse($response->headers->has('Location'));
        static::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('FRAMEWORK__OAUTH_INVALID_REDIRECT_URI', $content['errors'][0]['code']);
        static::assertSame('Redirect URL is not registered for this application.', $content['errors'][0]['detail']);
    }

    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function invalidRedirectEndpointProvider(): iterable
    {
        yield 'authorization entry' => ['GET', '/api/oauth/authorize', false, 'application/json'];
        yield 'browser entry also receives JSON' => ['GET', '/api/oauth/authorize', false, 'text/html'];
        yield 'consent info' => ['GET', '/api/oauth/authorize/info', true, 'application/json'];
        yield 'consent approval' => ['POST', '/api/oauth/authorize', true, 'application/json'];
    }

    public function testPlainCodeChallengeMethodIsRedirectedBackAsError(): void
    {
        $browser = $this->getBrowser(false);
        $browser->followRedirects(false);
        $browser->request('GET', '/api/oauth/authorize', [...$this->authorizationQuery(), 'code_challenge_method' => 'plain']);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), (string) $response->getContent());
        $location = (string) $response->headers->get('Location');
        static::assertStringStartsWith(self::REDIRECT_URI . '?', $location);
        static::assertStringContainsString('error=invalid_request', $location);
        static::assertStringContainsString('state=xyz', $location);
    }

    public function testPublicClientCannotUsePasswordGrant(): void
    {
        $response = $this->requestToken([
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'username' => 'admin',
            'password' => 'shopware',
        ]);

        static::assertArrayNotHasKey('access_token', $response);
        static::assertSame((string) OAuthServerException::unauthorizedClient()->getCode(), $response['errors'][0]['code'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    private function authorizationQuery(): array
    {
        return [
            'response_type' => 'code',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'state' => 'xyz',
            'scope' => 'write',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    private function approve(bool $approved, ?KernelBrowser $browser = null): string
    {
        $browser ??= $this->getBrowser();
        $payload = [...$this->authorizationQuery(), 'approved' => $approved];
        $browser->request('POST', '/api/oauth/authorize', $payload, [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($content['redirectUri'] ?? null);

        return $content['redirectUri'];
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function requestToken(array $payload): array
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/oauth/token', $payload, [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        return json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function getUserIdOfBrowser(KernelBrowser $browser): string
    {
        $bearer = substr((string) $browser->getServerParameter('HTTP_Authorization'), \strlen('Bearer '));
        static::assertNotSame('', $bearer);
        $token = static::getContainer()->get('shopware.jwt_config')->parser()->parse($bearer);
        static::assertInstanceOf(UnencryptedToken::class, $token);

        return (string) $token->claims()->get('sub');
    }
}
