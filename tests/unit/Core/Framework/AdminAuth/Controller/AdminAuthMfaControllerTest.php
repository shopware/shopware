<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Controller;

use Doctrine\DBAL\Connection;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Controller\AdminAuthMfaController;
use Shopware\Core\Framework\AdminAuth\Entity\UserMethod\AdminAuthUserMethodCollection;
use Shopware\Core\Framework\AdminAuth\Entity\UserMethod\AdminAuthUserMethodEntity;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnChallengeStore;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnService;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminAuthMfaController::class)]
class AdminAuthMfaControllerTest extends TestCase
{
    private const APP_SECRET = 'test-app-secret';

    private string $userId;

    private SecretEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->userId = Uuid::randomHex();
        $this->encryptor = new SecretEncryptor(self::APP_SECRET);
    }

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testEveryRouteRequiresTheFeatureFlag(): void
    {
        $controller = $this->createController();
        $context = $this->adminContext();
        $request = $this->verifiedRequest();

        $routes = [
            fn () => $controller->listMethods($context, $request),
            fn () => $controller->totpRegisterOptions($context, $request),
            fn () => $controller->totpRegisterVerify($context, $request),
            fn () => $controller->webauthnRegisterOptions($context, $request),
            fn () => $controller->webauthnRegisterVerify($context, $request),
            fn () => $controller->generateRecoveryCodes($context, $request),
            fn () => $controller->deleteMethod(Uuid::randomHex(), $context, $request),
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

    public function testRoutesRequireAnAdminUserContext(): void
    {
        $this->expectExceptionObject(AdminAuthException::missingUserContext());

        // A system context (no admin user) must be rejected.
        $this->createController()->listMethods(Context::createDefaultContext(), $this->verifiedRequest());
    }

    public function testEnrollmentRoutesRequireAFreshlyVerifiedUser(): void
    {
        $this->expectExceptionObject(AdminAuthException::userNotVerified());

        // The request carries no user-verified OAuth scope.
        $this->createController()->totpRegisterOptions($this->adminContext(), new Request());
    }

    public function testEnrollmentIntoADisabledMethodIsImpossible(): void
    {
        $methodSettings = new MethodSettingsService(new StaticSystemConfigService(), ['totp' => false]);

        $this->expectExceptionObject(AdminAuthException::methodDisabled('totp'));

        $this->createController(methodSettings: $methodSettings)
            ->totpRegisterOptions($this->adminContext(), $this->verifiedRequest());
    }

    public function testListMethodsExposesMetadataButNeverSecretsOrCredentials(): void
    {
        $lastUsed = new \DateTimeImmutable('2026-01-01 12:00:00');

        $totp = $this->userMethod('totp', label: 'Authenticator app', secret: 'encrypted-secret');
        $totp->setLastUsedAt($lastUsed);
        $recovery = $this->userMethod('recovery_codes', label: 'Recovery codes', credential: [
            'codes' => [
                ['hash' => 'hash-a', 'usedAt' => null],
                ['hash' => 'hash-b', 'usedAt' => '2026-01-01T00:00:00+00:00'],
            ],
        ]);

        $repository = $this->repository([new AdminAuthUserMethodCollection([$totp, $recovery])]);

        $response = $this->createController($repository)->listMethods($this->adminContext(), new Request());

        static::assertSame([
            'methods' => [
                [
                    'id' => $totp->getId(),
                    'type' => 'totp',
                    'active' => true,
                    'label' => 'Authenticator app',
                    'lastUsedAt' => $lastUsed->format(\DateTimeInterface::ATOM),
                ],
                [
                    'id' => $recovery->getId(),
                    'type' => 'recovery_codes',
                    'active' => true,
                    'label' => 'Recovery codes',
                    'lastUsedAt' => null,
                    'remaining' => 1,
                ],
            ],
        ], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testTotpRegisterOptionsCreatesAnInactiveEnrollmentAndReturnsTheSecretOnce(): void
    {
        $repository = $this->repository([]);

        $response = $this->createController($repository)
            ->totpRegisterOptions($this->adminContext(), $this->verifiedRequest(['label' => 'My phone']));

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsString($data['id']);
        static::assertIsString($data['secret']);
        static::assertNotSame('', $data['secret']);
        static::assertIsString($data['otpauthUri']);
        static::assertStringStartsWith('otpauth://totp/', $data['otpauthUri']);
        static::assertStringContainsString('My%20phone', $data['otpauthUri']);

        $created = $repository->creates[0][0];
        static::assertSame($data['id'], $created['id']);
        static::assertSame($this->userId, $created['userId']);
        static::assertSame('totp', $created['type']);
        static::assertFalse($created['active'], 'the enrollment must stay inactive until the code is verified');
        static::assertSame('My phone', $created['label']);
        static::assertIsString($created['secret']);
        static::assertSame($data['secret'], $this->encryptor->decrypt($created['secret']), 'the secret must be stored encrypted');
    }

    public function testTotpRegisterVerifyActivatesTheEnrollmentWithAValidCode(): void
    {
        $secret = TOTP::generate()->getSecret();
        $method = $this->userMethod('totp', active: false, secret: $this->encryptor->encrypt($secret));
        $repository = $this->repository([new AdminAuthUserMethodCollection([$method])]);

        $response = $this->createController($repository)->totpRegisterVerify(
            $this->adminContext(),
            $this->verifiedRequest(['id' => $method->getId(), 'code' => TOTP::createFromSecret($secret)->now()])
        );

        static::assertSame(
            ['success' => true],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
        static::assertSame([[['id' => $method->getId(), 'active' => true]]], $repository->updates);
    }

    public function testTotpRegisterVerifyRejectsMalformedInput(): void
    {
        // No repository search is queued: malformed input must be rejected before any lookup.
        $controller = $this->createController();

        $this->expectExceptionObject(AdminAuthException::invalidMfaCode());

        $controller->totpRegisterVerify(
            $this->adminContext(),
            $this->verifiedRequest(['id' => 'not-a-uuid', 'code' => '123456'])
        );
    }

    public function testTotpRegisterVerifyRejectsAWrongCode(): void
    {
        $secret = TOTP::generate()->getSecret();
        $method = $this->userMethod('totp', active: false, secret: $this->encryptor->encrypt($secret));
        $repository = $this->repository([new AdminAuthUserMethodCollection([$method])]);
        $controller = $this->createController($repository);

        $wrongCode = TOTP::createFromSecret($secret)->now() === '000000' ? '000001' : '000000';

        try {
            $controller->totpRegisterVerify(
                $this->adminContext(),
                $this->verifiedRequest(['id' => $method->getId(), 'code' => $wrongCode])
            );
            static::fail('a wrong TOTP code must be rejected');
        } catch (AdminAuthException $exception) {
            static::assertSame(AdminAuthException::INVALID_MFA_CODE, $exception->getErrorCode());
        }

        static::assertSame([], $repository->updates, 'the enrollment must not be activated');
    }

    public function testGenerateRecoveryCodesReturnsTenFreshCodesAndReplacesTheOldSet(): void
    {
        $existingId = Uuid::randomHex();
        $repository = $this->repository([[$existingId]]);

        $response = $this->createController($repository)
            ->generateRecoveryCodes($this->adminContext(), $this->verifiedRequest());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        $codes = $data['codes'];
        static::assertIsArray($codes);
        static::assertCount(10, $codes);
        foreach ($codes as $code) {
            static::assertIsString($code);
            static::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code);
        }

        static::assertSame([[['id' => $existingId]]], $repository->deletes, 'regenerating must delete the previous set');

        $created = $repository->creates[0][0];
        static::assertSame('recovery_codes', $created['type']);
        static::assertTrue($created['active']);
        static::assertIsArray($created['credential']);
        $stored = $created['credential']['codes'];
        static::assertIsArray($stored);
        static::assertCount(10, $stored);
        static::assertIsArray($stored[0]);
        static::assertNull($stored[0]['usedAt']);
        static::assertIsString($stored[0]['hash']);
        static::assertIsString($codes[0]);
        static::assertTrue(
            password_verify(str_replace('-', '', $codes[0]), $stored[0]['hash']),
            'only a hash of the canonical (dash-stripped) code must be stored'
        );
    }

    public function testDeleteMethodTreatsAnInvalidIdAsANoOp(): void
    {
        // No repository search is queued: an invalid id must not trigger a lookup.
        $repository = $this->repository([]);

        $response = $this->createController($repository)
            ->deleteMethod('not-a-uuid', $this->adminContext(), $this->verifiedRequest());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([], $repository->deletes);
    }

    public function testDeleteMethodDeletesAnOwnedMethod(): void
    {
        $method = $this->userMethod('totp');
        $repository = $this->repository([new AdminAuthUserMethodCollection([$method])]);

        $response = $this->createController($repository)
            ->deleteMethod($method->getId(), $this->adminContext(), $this->verifiedRequest());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $method->getId()]]], $repository->deletes);
    }

    public function testDeleteMethodIgnoresForeignMethods(): void
    {
        // The user filter turns a foreign method id into an empty result.
        $repository = $this->repository([new AdminAuthUserMethodCollection([])]);

        $response = $this->createController($repository)
            ->deleteMethod(Uuid::randomHex(), $this->adminContext(), $this->verifiedRequest());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([], $repository->deletes);
    }

    public function testWebauthnRegisterOptionsIssuesAUserBoundChallenge(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['username' => 'jane', 'name' => 'Jane Doe']);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $challengeStore = new WebAuthnChallengeStore(self::APP_SECRET, new MockClock());

        $response = $this->createController(connection: $connection, challengeStore: $challengeStore)
            ->webauthnRegisterOptions($this->adminContext(), $this->verifiedRequest());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertIsArray($data['options']);
        static::assertSame('localhost', $data['options']['rp']['id']);
        static::assertSame('jane', $data['options']['user']['name']);
        static::assertSame('Jane Doe', $data['options']['user']['displayName']);

        static::assertIsString($data['challengeToken']);
        $optionsJson = $challengeStore->consume($data['challengeToken'], WebAuthnChallengeStore::PURPOSE_REGISTER, $this->userId);
        static::assertNotNull($optionsJson, 'the challenge token must be consumable for the enrolling user');
        static::assertSame($data['options'], json_decode($optionsJson, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testWebauthnRegisterVerifyWithoutAChallengeFails(): void
    {
        $this->expectExceptionObject(AdminAuthException::webAuthnRegistrationFailed('No active registration challenge.'));

        $this->createController()->webauthnRegisterVerify(
            $this->adminContext(),
            $this->verifiedRequest(['credential' => '{"id":"abc"}'])
        );
    }

    /**
     * @param StaticEntityRepository<AdminAuthUserMethodCollection>|null $repository
     */
    private function createController(
        ?StaticEntityRepository $repository = null,
        ?MethodSettingsService $methodSettings = null,
        ?Connection $connection = null,
        ?WebAuthnChallengeStore $challengeStore = null,
    ): AdminAuthMfaController {
        return new AdminAuthMfaController(
            $repository ?? $this->repository([]),
            $this->encryptor,
            new WebAuthnService('localhost', 'Shopware Admin', ['http://localhost']),
            $challengeStore ?? new WebAuthnChallengeStore(self::APP_SECRET, new MockClock()),
            $methodSettings ?? new MethodSettingsService(new StaticSystemConfigService()),
            $connection ?? static::createStub(Connection::class),
        );
    }

    /**
     * @param array<mixed> $searches
     *
     * @return StaticEntityRepository<AdminAuthUserMethodCollection>
     */
    private function repository(array $searches): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AdminAuthUserMethodCollection> $repository */
        $repository = new StaticEntityRepository($searches);

        return $repository;
    }

    private function adminContext(): Context
    {
        return new Context(new AdminApiSource($this->userId));
    }

    /**
     * @param array<string, string> $post
     */
    private function verifiedRequest(array $post = []): Request
    {
        $request = new Request(request: $post);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, ['admin', UserVerifiedScope::IDENTIFIER]);

        return $request;
    }

    /**
     * @param array<string, mixed>|null $credential
     */
    private function userMethod(
        string $type,
        bool $active = true,
        ?string $label = null,
        ?string $secret = null,
        ?array $credential = null,
    ): AdminAuthUserMethodEntity {
        $method = new AdminAuthUserMethodEntity();
        $method->setId(Uuid::randomHex());
        $method->setUniqueIdentifier($method->getId());
        $method->setUserId($this->userId);
        $method->setType($type);
        $method->setActive($active);
        $method->setLabel($label);
        $method->setSecret($secret);
        $method->setCredential($credential);

        return $method;
    }
}
