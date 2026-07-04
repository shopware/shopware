<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaPolicyService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaRateLimiter;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminSecondFactorGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\PasswordVerifier;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\RecoveryCodeVerifier;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\TotpVerifier;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\OAuth\FakeCryptKey;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\OAuth\Scope\AdminScope;
use Shopware\Core\Framework\Api\OAuth\Scope\WriteScope;
use Shopware\Core\Framework\Api\OAuth\ScopeRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminSecondFactorGrantTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    private string $userId;

    private string $pendingToken;

    private string $challengeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->userId = $this->fetchAdminUserId();
        $this->enrollTotp($this->userId, self::TOTP_SECRET);

        [$this->pendingToken, $this->challengeId] = $this->loginUntilPending();
    }

    public function testTotpCompletesLoginWithFullToken(): void
    {
        $code = TOTP::createFromSecret(self::TOTP_SECRET)->now();

        $response = $this->respondToSecondFactorGrant($this->createSecondFactorRequest($code));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = json_decode($response->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('access_token', $body);
        static::assertArrayHasKey('refresh_token', $body, 'the completed login must get a refresh token');

        $claims = $this->decodeTokenPayload($body['access_token']);
        static::assertContains(WriteScope::IDENTIFIER, $claims['scopes']);
        static::assertContains(AdminScope::IDENTIFIER, $claims['scopes']);
        static::assertNotContains(MfaPendingScope::IDENTIFIER, $claims['scopes']);
        static::assertSame($this->userId, $claims['sub']);

        static::assertSame(1, $this->fetchChallengeColumn('consumed'), 'a completed challenge must be burned');
    }

    public function testWrongCodeIncrementsAttemptsAndKeepsChallengeOpen(): void
    {
        try {
            $this->respondToSecondFactorGrant($this->createSecondFactorRequest('000000'));
            static::fail('expected an invalid_grant OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_grant', $exception->getErrorType());
        }

        static::assertSame(1, $this->fetchChallengeColumn('attempts'));
        static::assertSame(0, $this->fetchChallengeColumn('consumed'));
    }

    public function testExpiredChallengeIsRejected(): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_auth_mfa_challenge SET expires_at = :past WHERE id = :id',
            [
                'past' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s.v'),
                'id' => Uuid::fromHexToBytes($this->challengeId),
            ]
        );

        $code = TOTP::createFromSecret(self::TOTP_SECRET)->now();

        try {
            $this->respondToSecondFactorGrant($this->createSecondFactorRequest($code));
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('Invalid or expired MFA challenge.', $exception->getHint());
        }
    }

    public function testConsumedChallengeIsRejected(): void
    {
        $code = TOTP::createFromSecret(self::TOTP_SECRET)->now();

        // First completion succeeds ...
        $this->respondToSecondFactorGrant($this->createSecondFactorRequest($code));

        // ... replaying the same pending token must fail.
        try {
            $this->respondToSecondFactorGrant($this->createSecondFactorRequest($code));
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
        }
    }

    public function testExhaustedAttemptsConsumeTheChallenge(): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_auth_mfa_challenge SET attempts = 5 WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->challengeId)]
        );

        $code = TOTP::createFromSecret(self::TOTP_SECRET)->now();

        try {
            $this->respondToSecondFactorGrant($this->createSecondFactorRequest($code));
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('Too many second-factor attempts.', $exception->getHint());
        }

        static::assertSame(1, $this->fetchChallengeColumn('consumed'));
    }

    public function testMethodNotAllowedByChallengeIsRejected(): void
    {
        try {
            $this->respondToSecondFactorGrant($this->createSecondFactorRequest('ABCD-1234', method: 'recovery_codes'));
            static::fail('expected an invalid_request OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('invalid_request', $exception->getErrorType());
            static::assertSame('This factor is not allowed for the challenge.', $exception->getHint());
        }
    }

    public function testMissingPendingTokenIsRejected(): void
    {
        $request = $this->createSecondFactorRequest('123456');
        $request->headers->remove('Authorization');

        try {
            $this->respondToSecondFactorGrant($request);
            static::fail('expected an access_denied OAuth error');
        } catch (OAuthServerException $exception) {
            static::assertSame('access_denied', $exception->getErrorType());
            static::assertSame('Missing pending token.', $exception->getHint());
        }
    }

    /**
     * Runs the primary grant against the MFA-enrolled user and returns [pendingToken, challengeId].
     *
     * @return array{0: string, 1: string}
     */
    private function loginUntilPending(): array
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', AdminPrimaryGrant::TYPE);
        $request->request->set('scope', 'write');
        $request->request->set('method', 'password');
        $request->request->set('username', 'admin');
        $request->request->set('password', 'shopware');

        $psrHttpFactory = static::getContainer()->get(PsrHttpFactory::class);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('test-encryption-key');

        $responseType = $this->createPrimaryGrant()->respondToAccessTokenRequest(
            $psrHttpFactory->createRequest($request),
            $bearerResponse,
            new \DateInterval('PT10M')
        );

        $response = $responseType->generateHttpResponse(new Psr7Response());
        $body = json_decode($response->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('access_token', $body);
        static::assertArrayNotHasKey('refresh_token', $body);

        $claims = $this->decodeTokenPayload($body['access_token']);

        $challengeId = null;
        foreach ($claims['scopes'] as $scope) {
            if (str_starts_with($scope, AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX)) {
                $challengeId = substr($scope, \strlen(AdminPrimaryGrant::CHALLENGE_SCOPE_PREFIX));
            }
        }

        static::assertIsString($challengeId, 'the pending token must carry the challenge marker scope');

        return [$body['access_token'], $challengeId];
    }

    private function respondToSecondFactorGrant(Request $request): Psr7Response
    {
        $grant = $this->createSecondFactorGrant();

        $psrHttpFactory = static::getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('test-encryption-key');

        $responseType = $grant->respondToAccessTokenRequest($psr7Request, $bearerResponse, new \DateInterval('PT1H'));

        $response = $responseType->generateHttpResponse(new Psr7Response());
        static::assertInstanceOf(Psr7Response::class, $response);

        return $response;
    }

    private function createSecondFactorRequest(string $code, string $method = 'totp'): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->headers->set('Authorization', 'Bearer ' . $this->pendingToken);
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', AdminSecondFactorGrant::TYPE);
        $request->request->set('method', $method);
        $request->request->set('code', $code);

        return $request;
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

        $this->configureGrant($grant);

        return $grant;
    }

    private function createSecondFactorGrant(): AdminSecondFactorGrant
    {
        $secretEncryptor = new SecretEncryptor((string) static::getContainer()->getParameter('kernel.secret'));

        $clock = new NativeClock();

        $grant = new AdminSecondFactorGrant(
            [new TotpVerifier($this->connection, $secretEncryptor, $clock), new RecoveryCodeVerifier($this->connection, $clock)],
            new RefreshTokenRepository($this->connection, $clock),
            new MfaChallengeStore($this->connection, $clock),
            JWTConfigurationFactory::createJWTConfiguration(),
            new MfaRateLimiter(new ArrayAdapter()),
            $clock
        );

        $this->configureGrant($grant);

        return $grant;
    }

    private function configureGrant(AdminPrimaryGrant|AdminSecondFactorGrant $grant): void
    {
        $grant->setClientRepository(static::getContainer()->get(ClientRepository::class));
        $grant->setScopeRepository(static::getContainer()->get(ScopeRepository::class));
        $grant->setAccessTokenRepository(static::getContainer()->get(AccessTokenRepository::class));
        $grant->setPrivateKey(new FakeCryptKey(JWTConfigurationFactory::createJWTConfiguration()));
        $grant->setRefreshTokenTTL(new \DateInterval('P1W'));
        $grant->setDefaultScope('');
    }

    private function fetchChallengeColumn(string $column): int
    {
        $value = $this->connection->fetchOne(
            \sprintf('SELECT %s FROM admin_auth_mfa_challenge WHERE id = :id', $column),
            ['id' => Uuid::fromHexToBytes($this->challengeId)]
        );

        static::assertNotFalse($value);

        return (int) $value;
    }
}
