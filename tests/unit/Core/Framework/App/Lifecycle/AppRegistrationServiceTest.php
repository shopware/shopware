<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppRegistrationService::class)]
class AppRegistrationServiceTest extends TestCase
{
    /**
     * The app's name in manifest.xml; the service reports it back as the {{ appName }} in every
     * registration failure, so it is also the first argument to the expected exceptions.
     */
    private const APP_NAME = 'test';

    private const SECRET_ACCESS_KEY = 's3cr3t-4cc3s-k3y';

    /**
     * The secret the shop currently holds for the app before this registration runs. The handshake the
     * app answers with must mint a *different* one, otherwise the rotation is rejected.
     */
    private const CURRENT_APP_SECRET = '4pp-s3cr3t';

    /**
     * The proof the app server echoes back. parseResponse() accepts the handshake only when this matches
     * the proof the handshake itself computed, so the stub returns the same value on both sides.
     */
    private const MATCHING_PROOF = 'proof';

    private const CONFIRMATION_URL = 'https://app.server/confirm';

    private HandshakeFactory&MockObject $handshakeFactoryMock;

    private MockHandler $mockHandler;

    /**
     * @var EntityRepository<AppCollection>&MockObject
     */
    private EntityRepository&MockObject $appRepositoryMock;

    private AppRegistrationService $appRegistrationService;

    private Meter&MockObject $meterMock;

    private AppEntity $testApp;

    protected function setUp(): void
    {
        $this->handshakeFactoryMock = $this->createMock(HandshakeFactory::class);
        $this->meterMock = $this->createMock(Meter::class);

        $this->mockHandler = new MockHandler([]);
        $this->appRepositoryMock = $this->createMock(EntityRepository::class);
        $this->testApp = $this->createAppEntity();
        $this->appRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'app',
                1,
                new AppCollection([$this->testApp]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $shopIdProviderMock = $this->createMock(ShopIdProvider::class);
        $shopIdProviderMock->method('getShopId')->willReturn(ShopId::v2('shop-id'));

        $this->appRegistrationService = new AppRegistrationService(
            $this->handshakeFactoryMock,
            new Client(['handler' => $this->mockHandler]),
            $this->appRepositoryMock,
            'https://shopware.swag',
            $shopIdProviderMock,
            '6.5.2.0',
            new NativeClock(),
            new NullLogger(),
            $this->meterMock,
        );
    }

    public function testDoesNotRegisterAtAppServerIfManifestHasNoSetup(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest_no_setup.xml');

        $this->handshakeFactoryMock->expects($this->never())->method('create');

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfTheHandshakeFailsWithoutAResponse(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $handshakeRequest = $this->stubHandshake();

        // Transport error before any response (DNS, connection refused, …): with no response body to read
        // an error out of, the failure message falls back to the exception's own message.
        $this->mockHandler->append(
            new RequestException('Unknown app', $handshakeRequest),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'Unknown app'));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfTheHandshakeFailsWithAnErrorResponse(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $handshakeRequest = $this->stubHandshake();

        // The app answered with a 4xx whose body names the error; that error becomes the failure reason.
        $this->mockHandler->append(
            new RequestException(
                '',
                $handshakeRequest,
                $this->appServerResponse(['error' => 'Database error on app server'], SymfonyResponse::HTTP_BAD_REQUEST)
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'Database error on app server'));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesError(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $this->stubHandshake();

        // No transport error, but the (parsed) response body carries an `error` field, which is surfaced as-is.
        $this->mockHandler->append(
            $this->appServerResponse(['error' => 'Database error on app server'], SymfonyResponse::HTTP_BAD_REQUEST),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'Database error on app server'));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfReturnedSecretMatchesTheOldOne(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $this->testApp->setAppSecret(self::CURRENT_APP_SECRET);
        $this->stubHandshake(self::MATCHING_PROOF);

        // The app echoes back the secret we already hold. A rotation to the same value is meaningless, so it
        // is rejected before anything is persisted.
        $this->mockHandler->append(
            $this->appServerResponse([
                'proof' => self::MATCHING_PROOF,
                'secret' => self::CURRENT_APP_SECRET,
                'confirmation_url' => self::CONFIRMATION_URL,
            ], SymfonyResponse::HTTP_BAD_REQUEST),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'The new app secret returned from the App must be different from the current one.'));

        $this->registerTestApp($manifest);
    }

    public function testSuccessfullyRegisters(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $this->stubHandshake(self::MATCHING_PROOF);

        // App mints a new secret and a confirmation URL; the second (empty) response is the 2xx confirm.
        $this->mockHandler->append(
            $this->appServerResponse([
                'proof' => self::MATCHING_PROOF,
                'secret' => self::CURRENT_APP_SECRET,
                'confirmation_url' => self::CONFIRMATION_URL,
            ], SymfonyResponse::HTTP_BAD_REQUEST),
        );
        $this->mockHandler->append(new Response());

        // Two-phase commit: the new secret is saved as *unconfirmed* (with a timestamp) before the confirm
        // call, then promoted to app_secret and the unconfirmed fields cleared once the app accepts it.
        $update = $this->exactly(2);
        $this->appRepositoryMock->expects($update)
            ->method('update')
            ->willReturnCallback(function (array $payload) use ($update): EntityWrittenContainerEvent {
                $isFirstUpdate = $update->numberOfInvocations() === 1;
                if ($isFirstUpdate) {
                    static::assertSame($this->testApp->getId(), $payload[0]['id']);
                    static::assertSame([self::CURRENT_APP_SECRET], $payload[0]['unconfirmedAppSecrets']);
                    static::assertInstanceOf(\DateTimeInterface::class, $payload[0]['unconfirmedAppSecretsUpdatedAt']);
                } else {
                    static::assertSame(
                        [[
                            'id' => $this->testApp->getId(),
                            'appSecret' => self::CURRENT_APP_SECRET,
                            'unconfirmedAppSecrets' => null,
                            'unconfirmedAppSecretsUpdatedAt' => null,
                        ]],
                        $payload
                    );
                }

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $this->registerTestApp($manifest);
    }

    public function testRegisterAppKeepsPendingSecretWhenHandshakeFailsWithClientError(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $handshakeRequest = $this->stubHandshake();

        // A 4xx on the registration handshake is not the same as the app rejecting a confirm, so it must not
        // clear a pending secret left behind by an earlier rotation that ended without a clear answer.
        $this->mockHandler->append(
            new ClientException('app rejected the handshake', $handshakeRequest, new Response(SymfonyResponse::HTTP_FORBIDDEN)),
        );

        $this->appRepositoryMock->expects($this->never())->method('update');

        $this->expectException(AppException::class);
        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesInvalidJson(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $this->stubHandshake();

        $this->mockHandler->append(new Response(body: '{invalid-json: test,}'));

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'JSON response could not be decoded'));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionWithStatusCodeAndResponseBody(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $handshakeRequest = $this->stubHandshake();

        // A response with no `error` field to surface: the failure quotes the raw status code and body instead.
        $responseBody = json_encode(['some' => 'data', 'without' => 'error field'], \JSON_THROW_ON_ERROR);

        $this->mockHandler->append(
            new RequestException('Unknown app', $handshakeRequest, new Response(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, body: $responseBody)),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'Got status code 500, with response: ' . $responseBody));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesNoProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        $this->stubHandshake(Uuid::randomHex());

        // `proof` is present but not a string, so it counts as no proof at all.
        $this->mockHandler->append(
            $this->appServerResponse([
                'proof' => 1337,
                'secret' => self::CURRENT_APP_SECRET,
                'confirmation_url' => self::CONFIRMATION_URL,
            ], SymfonyResponse::HTTP_BAD_REQUEST),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'The app server provided no proof'));

        $this->registerTestApp($manifest);
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesInvalidProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');
        // The handshake expects one proof; the app server returns a different one.
        $this->stubHandshake(Uuid::randomHex());

        $this->mockHandler->append(
            $this->appServerResponse([
                'proof' => Uuid::randomHex(),
                'secret' => self::CURRENT_APP_SECRET,
                'confirmation_url' => self::CONFIRMATION_URL,
            ], SymfonyResponse::HTTP_BAD_REQUEST),
        );

        $this->expectExceptionObject(AppException::registrationFailed(self::APP_NAME, 'The app server provided an invalid proof'));

        $this->registerTestApp($manifest);
    }

    private function registerTestApp(Manifest $manifest): void
    {
        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), self::SECRET_ACCESS_KEY, Context::createDefaultContext());
    }

    /**
     * Wire the handshake factory to return a handshake whose request the service will send. The request's
     * contents are never asserted — it only needs to be a non-null request the MockHandler can answer (and
     * that a RequestException can carry) — so a single shared instance stands in for all callers.
     *
     * Pass the proof the app server is expected to echo back when the test reaches the proof check; omit it
     * for tests that fail before the response is parsed.
     *
     * @return RequestInterface the request the service will send, for tests that need to attach it to a failure
     */
    private function stubHandshake(?string $appProof = null): RequestInterface
    {
        $handshakeRequest = (new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            self::APP_NAME,
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        ))->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($handshakeRequest);
        if ($appProof !== null) {
            $handshakeMock->method('fetchAppProof')->willReturn($appProof);
        }

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        return $handshakeRequest;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function appServerResponse(array $body, int $status = SymfonyResponse::HTTP_OK): Response
    {
        return new Response($status, body: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function createAppEntity(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName(self::APP_NAME);

        $integration = new IntegrationEntity();
        $integration->setId(Uuid::randomHex());
        $integration->setLabel('test-integration');
        $integration->setAccessKey('test-access-key');
        $integration->setSecretAccessKey('test-secret-key');

        $app->setIntegration($integration);

        return $app;
    }
}
