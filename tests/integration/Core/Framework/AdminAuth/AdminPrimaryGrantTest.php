<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaPolicyService;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\PasswordVerifier;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminPrimaryGrantTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGrantIsNotRegisteredWhenFeatureFlagIsInactive(): void
    {
        Feature::skipTestIfActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/oauth/token', [
            'grant_type' => AdminPrimaryGrant::TYPE,
            'client_id' => 'administration',
            'method' => 'password',
            'username' => 'admin',
            'password' => 'shopware',
        ]);

        static::assertNotFalse($browser->getResponse()->getContent());
        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $response = json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(
            OAuthServerException::unsupportedGrantType()->getMessage(),
            $response['errors'][0]['title'] ?? null
        );
    }

    public function testGrantIsRegisteredWhenFeatureFlagIsActive(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request('POST', '/api/oauth/token', [
            'grant_type' => AdminPrimaryGrant::TYPE,
            'client_id' => 'administration',
            'method' => 'password',
            'username' => 'admin',
            'password' => 'shopware',
        ]);

        static::assertNotFalse($browser->getResponse()->getContent());
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $response = json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('access_token', $response);
        static::assertArrayHasKey('refresh_token', $response);
    }

    public function testPasswordHappyPathIssuesFullToken(): void
    {
        $response = $this->respondToPrimaryGrant($this->createTokenRequest());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('Bearer', $body['token_type']);
        static::assertArrayHasKey('access_token', $body);
        static::assertArrayHasKey('refresh_token', $body, 'a login without MFA must get a refresh token');

        $claims = $this->decodeTokenPayload($body['access_token']);
        static::assertContains(WriteScope::IDENTIFIER, $claims['scopes']);
        static::assertContains(UserVerifiedScope::IDENTIFIER, $claims['scopes']);
        static::assertNotContains(MfaPendingScope::IDENTIFIER, $claims['scopes']);
    }

    public function testInvalidPasswordIsRejected(): void
    {
        try {
            $this->respondToPrimaryGrant($this->createTokenRequest(password: 'wrong-password'));
            static::fail('expected an invalid_grant OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_grant', $exception->getErrorType());
        }
    }

    public function testMfaEnrolledUserGetsPendingTokenWithoutRefreshToken(): void
    {
        $userId = $this->fetchAdminUserId();
        $this->enrollTotp($userId, 'JBSWY3DPEHPK3PXP');

        $response = $this->respondToPrimaryGrant($this->createTokenRequest());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('access_token', $body);
        static::assertArrayNotHasKey('refresh_token', $body, 'a pending login must not get a refresh token');

        $claims = $this->decodeTokenPayload($body['access_token']);
        static::assertContains(MfaPendingScope::IDENTIFIER, $claims['scopes']);
        static::assertNotContains(WriteScope::IDENTIFIER, $claims['scopes'], 'the pending token must be powerless');

        $methodsScope = $this->extractScopeWithPrefix($claims['scopes'], AdminPrimaryGrant::METHODS_SCOPE_PREFIX);
        static::assertSame(AdminPrimaryGrant::METHODS_SCOPE_PREFIX . 'totp', $methodsScope);

        $challengeScope = $this->extractScopeWithPrefix($claims['scopes'], AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX);
        $challengeId = substr((string) $challengeScope, \strlen(AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX));

        $challengeRow = $this->connection->fetchAssociative(
            'SELECT * FROM admin_auth_mfa_challenge WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($challengeId)]
        );

        static::assertIsArray($challengeRow, 'the pending login must insert a challenge row');
        static::assertSame($userId, Uuid::fromBytesToHex($challengeRow['user_id']));
        static::assertSame($claims['jti'], $challengeRow['pending_jti'], 'the challenge must be bound to the token jti');
        static::assertSame(['totp'], json_decode((string) $challengeRow['allowed_methods'], true));
        static::assertSame(0, (int) $challengeRow['attempts']);
        static::assertSame(0, (int) $challengeRow['consumed']);
    }

    public function testUnsupportedLoginMethodIsRejected(): void
    {
        try {
            $this->respondToPrimaryGrant($this->createTokenRequest(method: 'carrier-pigeon'));
            static::fail('expected an invalid_request OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
            static::assertSame('Unsupported login method "carrier-pigeon".', $exception->getHint());
        }
    }

    private function respondToPrimaryGrant(Request $request): Psr7Response
    {
        $grant = $this->createPrimaryGrant();

        $psrHttpFactory = static::getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('test-encryption-key');

        $responseType = $grant->respondToAccessTokenRequest($psr7Request, $bearerResponse, new \DateInterval('PT1H'));

        $response = $responseType->generateHttpResponse(new Psr7Response());
        static::assertInstanceOf(Psr7Response::class, $response);

        return $response;
    }

    private function createPrimaryGrant(): AdminPrimaryGrant
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $methodSettings = new MethodSettingsService($systemConfig);

        $grant = new AdminPrimaryGrant(
            [new PasswordVerifier($this->connection, $methodSettings)],
            new RefreshTokenRepository($this->connection, new NativeClock()),
            new MfaPolicyService($this->connection, $systemConfig, $methodSettings),
            new MfaChallengeStore($this->connection, new NativeClock())
        );

        $grant->setClientRepository(static::getContainer()->get(ClientRepository::class));
        $grant->setScopeRepository(static::getContainer()->get(ScopeRepository::class));
        $grant->setAccessTokenRepository(static::getContainer()->get(AccessTokenRepository::class));
        $grant->setPrivateKey(new FakeCryptKey(JWTConfigurationFactory::createJWTConfiguration()));
        $grant->setRefreshTokenTTL(new \DateInterval('P1W'));
        $grant->setDefaultScope('');

        return $grant;
    }

    private function createTokenRequest(string $password = 'shopware', string $method = 'password'): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', AdminPrimaryGrant::TYPE);
        $request->request->set('scope', 'write user-verified');
        $request->request->set('method', $method);
        $request->request->set('username', 'admin');
        $request->request->set('password', $password);

        return $request;
    }

    /**
     * @param list<string> $scopes
     */
    private function extractScopeWithPrefix(array $scopes, string $prefix): ?string
    {
        foreach ($scopes as $scope) {
            if (str_starts_with($scope, $prefix)) {
                return $scope;
            }
        }

        return null;
    }
}
