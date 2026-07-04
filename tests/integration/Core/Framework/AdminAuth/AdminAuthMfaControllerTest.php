<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminSecondFactorGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminAuthMfaControllerTest extends TestCase
{
    use AdminAuthTestHelperTrait;
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testRoutesAreNotAvailableWithoutTheFeature(): void
    {
        Feature::skipTestIfActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/mfa/methods');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testTotpEnrollmentHappyPathEnforcesMfaOnTheNextLogin(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        // 1. Begin the enrollment: the secret is returned once, the method row is pending.
        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/options', ['label' => 'My phone']);
        $options = $this->assertJsonResponse($browser, Response::HTTP_OK);

        static::assertIsString($options['id']);
        static::assertStringStartsWith('otpauth://totp/', $options['otpauthUri']);

        // 2. Complete it with a valid code computed from the returned secret.
        $code = TOTP::createFromSecret($this->secretFrom($options))->now();
        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/verify', [
            'id' => $options['id'],
            'code' => $code,
        ]);
        $verify = $this->assertJsonResponse($browser, Response::HTTP_OK);
        static::assertTrue($verify['success']);

        // 3. The method is listed as active — and no secret material leaks.
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/mfa/methods');
        $list = $this->assertJsonResponse($browser, Response::HTTP_OK);

        static::assertCount(1, $list['methods']);
        static::assertSame($options['id'], $list['methods'][0]['id']);
        static::assertSame('totp', $list['methods'][0]['type']);
        static::assertTrue($list['methods'][0]['active']);
        static::assertSame('My phone', $list['methods'][0]['label']);
        static::assertStringNotContainsString('secret', (string) $browser->getResponse()->getContent());
        static::assertStringNotContainsString('credential', (string) $browser->getResponse()->getContent());

        // 4. The next primary login only gets a pending token and demands the second factor.
        $pending = $this->primaryLogin($browser);
        static::assertArrayNotHasKey('refresh_token', $pending);

        $claims = $this->decodeTokenPayload($pending['access_token']);
        static::assertContains(MfaPendingScope::IDENTIFIER, $claims['scopes']);
    }

    public function testTotpEnrollmentWithWrongCodeIsRejectedAndStaysInactive(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/options');
        $options = $this->assertJsonResponse($browser, Response::HTTP_OK);

        // An in-window collision between a random secret and a static code is practically impossible.
        $wrongCode = TOTP::createFromSecret($this->secretFrom($options))->now() === '000000' ? '111111' : '000000';
        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/verify', [
            'id' => $options['id'],
            'code' => $wrongCode,
        ]);
        $error = $this->assertJsonResponse($browser, Response::HTTP_BAD_REQUEST);
        static::assertSame(AdminAuthException::INVALID_MFA_CODE, $error['errors'][0]['code']);

        $active = $this->connection->fetchOne(
            'SELECT active FROM admin_auth_user_method WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($options['id'])]
        );
        static::assertSame(0, (int) $active, 'a failed verification must not activate the enrollment');

        // A login is still possible without MFA: the pending enrollment is no usable factor.
        $full = $this->primaryLogin($browser);
        static::assertArrayHasKey('refresh_token', $full);
    }

    public function testRecoveryCodesAreGeneratedOnceAndAreSingleUse(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        // Recovery codes only become a usable factor next to a real one.
        $this->enrollTotp($this->currentUserId(), 'JBSWY3DPEHPK3PXP');

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/recovery-codes');
        $generated = $this->assertJsonResponse($browser, Response::HTTP_OK);

        static::assertCount(10, $generated['codes']);

        // The plaintext codes are never stored — only hashes.
        $credential = $this->connection->fetchOne(
            'SELECT credential FROM admin_auth_user_method WHERE user_id = :uid AND type = :type',
            ['uid' => Uuid::fromHexToBytes($this->currentUserId()), 'type' => 'recovery_codes']
        );
        static::assertIsString($credential);
        foreach ($generated['codes'] as $plainCode) {
            static::assertStringNotContainsString(str_replace('-', '', $plainCode), $credential);
        }

        // First use completes a pending login ...
        $pending = $this->primaryLogin($browser);
        $completed = $this->secondFactorLogin($browser, $pending['access_token'], 'recovery_codes', $generated['codes'][0]);
        static::assertSame(Response::HTTP_OK, $completed->getStatusCode(), (string) $completed->getContent());

        $body = json_decode((string) $completed->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('refresh_token', $body);

        // ... replaying the same code on a fresh challenge must fail.
        $pending = $this->primaryLogin($browser);
        $replayed = $this->secondFactorLogin($browser, $pending['access_token'], 'recovery_codes', $generated['codes'][0]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $replayed->getStatusCode());

        // A different, unused code still works.
        $pending = $this->primaryLogin($browser);
        $other = $this->secondFactorLogin($browser, $pending['access_token'], 'recovery_codes', $generated['codes'][1]);
        static::assertSame(Response::HTTP_OK, $other->getStatusCode(), (string) $other->getContent());
    }

    public function testRegeneratingRecoveryCodesReplacesTheOldSet(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/recovery-codes');
        $first = $this->assertJsonResponse($browser, Response::HTTP_OK);

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/recovery-codes');
        $second = $this->assertJsonResponse($browser, Response::HTTP_OK);

        static::assertNotSame($first['codes'], $second['codes']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM admin_auth_user_method WHERE user_id = :uid AND type = :type',
            ['uid' => Uuid::fromHexToBytes($this->currentUserId()), 'type' => 'recovery_codes']
        );
        static::assertSame(1, (int) $count, 'regeneration must replace the previous set');
    }

    public function testOwnMethodCanBeDeleted(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();
        $userId = $this->currentUserId();
        $this->enrollTotp($userId, 'JBSWY3DPEHPK3PXP');

        $methodId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM admin_auth_user_method WHERE user_id = :uid',
            ['uid' => Uuid::fromHexToBytes($userId)]
        );
        static::assertIsString($methodId);

        $browser->request(Request::METHOD_DELETE, '/api/_action/admin-auth/mfa/methods/' . $methodId);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM admin_auth_user_method WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($methodId)]
        );
        static::assertSame(0, (int) $count);
    }

    public function testAnotherUsersMethodCannotBeDeleted(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $otherUserId = $this->createOtherUser();
        $this->enrollTotp($otherUserId, 'JBSWY3DPEHPK3PXP');

        $methodId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM admin_auth_user_method WHERE user_id = :uid',
            ['uid' => Uuid::fromHexToBytes($otherUserId)]
        );
        static::assertIsString($methodId);

        $browser->request(Request::METHOD_DELETE, '/api/_action/admin-auth/mfa/methods/' . $methodId);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM admin_auth_user_method WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($methodId)]
        );
        static::assertSame(1, (int) $count, 'a foreign method must survive the delete attempt');

        // The foreign method is also invisible in the listing.
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/mfa/methods');
        $list = $this->assertJsonResponse($browser, Response::HTTP_OK);
        static::assertNotContains($methodId, array_column($list['methods'], 'id'));
    }

    public function testDisabledMethodRejectsEnrollment(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        // The runtime layer of the same gate the YAML configuration enforces (the YAML hard gate
        // itself is a container parameter and covered by the MethodSettingsService unit tests).
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.adminAuth.methods',
            ['totp' => ['enabled' => false]]
        );

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/options');
        $error = $this->assertJsonResponse($browser, Response::HTTP_BAD_REQUEST);

        static::assertSame(AdminAuthException::METHOD_DISABLED, $error['errors'][0]['code']);
    }

    public function testEnrollmentMutationsRequireTheUserVerifiedScope(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        // The default browser token lacks the user-verified scope.
        $browser = $this->getBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/totp/register/options');
        $error = $this->assertJsonResponse($browser, Response::HTTP_FORBIDDEN);
        static::assertSame(AdminAuthException::USER_NOT_VERIFIED, $error['errors'][0]['code']);

        $browser->request(Request::METHOD_DELETE, '/api/_action/admin-auth/mfa/methods/' . Uuid::randomHex());
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());

        // The read-only listing works without re-verification.
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/mfa/methods');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
    }

    public function testWebauthnRegisterOptionsIssuesUserBoundChallenge(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/webauthn/register/options');
        $data = $this->assertJsonResponse($browser, Response::HTTP_OK);

        static::assertIsString($data['challengeToken']);
        static::assertIsArray($data['options']);
        static::assertNotEmpty($data['options']['challenge']);

        // webauthn-lib serializes the user handle base64url-encoded.
        $expectedUserHandle = rtrim(strtr(base64_encode($this->currentUserId()), '+/', '-_'), '=');
        static::assertSame($expectedUserHandle, $data['options']['user']['id']);
    }

    public function testWebauthnRegisterVerifyRejectsAMalformedAttestation(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/webauthn/register/options');
        $data = $this->assertJsonResponse($browser, Response::HTTP_OK);

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/webauthn/register/verify', [
            'credential' => '{"id": "garbage", "type": "public-key"}',
            'challengeToken' => $data['challengeToken'],
        ]);
        $error = $this->assertJsonResponse($browser, Response::HTTP_BAD_REQUEST);
        static::assertSame(AdminAuthException::WEBAUTHN_REGISTRATION_FAILED, $error['errors'][0]['code']);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM admin_auth_user_method WHERE user_id = :uid',
            ['uid' => Uuid::fromHexToBytes($this->currentUserId())]
        );
        static::assertSame(0, (int) $count, 'a failed attestation must not create a method row');
    }

    public function testWebauthnRegisterVerifyRejectsAMissingChallenge(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->verifiedBrowser();

        $browser->jsonRequest(Request::METHOD_POST, '/api/_action/admin-auth/mfa/webauthn/register/verify', [
            'credential' => '{"id": "garbage", "type": "public-key"}',
            'challengeToken' => 'tampered.token',
        ]);
        $error = $this->assertJsonResponse($browser, Response::HTTP_BAD_REQUEST);
        static::assertSame(AdminAuthException::WEBAUTHN_REGISTRATION_FAILED, $error['errors'][0]['code']);
    }

    /**
     * A browser authenticated as a fresh admin user whose token carries the `user-verified` scope
     * (as the admin's `verifyUserToken` re-verification would grant it).
     */
    private function verifiedBrowser(): TestBrowser
    {
        return $this->createClient(scopes: ['write', 'user-verified']);
    }

    /**
     * The id (hex) of the user the verified browser is authenticated as.
     */
    private function currentUserId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM `user` WHERE email = :mail',
            ['mail' => 'admin@example.com']
        );

        static::assertIsString($id);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * Runs the admin_primary grant over HTTP for the verified browser's user.
     *
     * @return array{access_token: string, refresh_token?: string}
     */
    private function primaryLogin(TestBrowser $browser): array
    {
        $username = $this->connection->fetchOne(
            'SELECT username FROM `user` WHERE email = :mail',
            ['mail' => 'admin@example.com']
        );
        static::assertIsString($username);

        $browser->request('POST', '/api/oauth/token', [
            'grant_type' => AdminPrimaryGrant::TYPE,
            'client_id' => 'administration',
            'method' => 'password',
            'username' => $username,
            'password' => 'shopware',
        ]);

        $body = $this->assertJsonResponse($browser, Response::HTTP_OK);
        static::assertIsString($body['access_token']);

        return $body;
    }

    private function secondFactorLogin(TestBrowser $browser, string $pendingToken, string $method, string $code): Response
    {
        $browser->request(
            'POST',
            '/api/oauth/token',
            [
                'grant_type' => AdminSecondFactorGrant::TYPE,
                'client_id' => 'administration',
                'method' => $method,
                'code' => $code,
            ],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $pendingToken]
        );

        $response = $browser->getResponse();
        static::assertInstanceOf(Response::class, $response);

        return $response;
    }

    private function createOtherUser(): string
    {
        $userId = Uuid::randomHex();

        $localeId = $this->connection->fetchOne(
            'SELECT locale_id FROM `user` WHERE email = :mail',
            ['mail' => 'admin@example.com']
        );
        static::assertIsString($localeId);

        $this->connection->insert('user', [
            'id' => Uuid::fromHexToBytes($userId),
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other-user@example.com',
            'username' => 'other-user-' . $userId,
            'password' => TestDefaults::HASHED_PASSWORD,
            'locale_id' => $localeId,
            'active' => 1,
            'admin' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $userId;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return non-empty-string
     */
    private function secretFrom(array $options): string
    {
        $secret = $options['secret'] ?? null;
        if (!\is_string($secret) || $secret === '') {
            static::fail('the options response must contain the TOTP secret');
        }

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    private function assertJsonResponse(TestBrowser $browser, int $expectedStatus): array
    {
        $response = $browser->getResponse();
        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertSame($expectedStatus, $response->getStatusCode(), $content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);

        return $data;
    }
}
