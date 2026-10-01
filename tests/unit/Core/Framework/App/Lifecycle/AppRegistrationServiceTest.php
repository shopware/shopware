<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
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
    private HandshakeFactory&MockObject $handshakeFactoryMock;

    private MockHandler $mockHandler;

    /**
     * @var EntityRepository<AppCollection>&Stub
     */
    private EntityRepository&Stub $appRepositoryMock;

    private AppRegistrationService $appRegistrationService;

    private AppEntity $testApp;

    protected function setUp(): void
    {
        $this->handshakeFactoryMock = $this->createMock(HandshakeFactory::class);

        $this->mockHandler = new MockHandler([]);
        $this->appRepositoryMock = static::createStub(EntityRepository::class);
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

        $this->appRegistrationService = $this->createService($this->appRepositoryMock);
    }

    public function testDoesNotRegisterAtAppServerIfManifestHasNoSetup(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest_no_setup.xml');

        $this->handshakeFactoryMock->expects($this->never())->method('create');

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfStoreHandshakeFails(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new StoreHandshake(
            'https://shopware.swag',
            'http://app.server/register',
            'test',
            'shop-id',
            static::createStub(StoreClient::class),
            '6.5.2.0',
            new NativeClock(),
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(StoreHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new RequestException('Unknown app', $registrationRequest),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'Unknown app'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfPrivateHandshakeFails(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new RequestException(
                '',
                $registrationRequest,
                new Response(
                    SymfonyResponse::HTTP_BAD_REQUEST,
                    body: json_encode(['error' => 'Database error on app server'], \JSON_THROW_ON_ERROR)
                )
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'Database error on app server'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesError(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode(['error' => 'Database error on app server'], \JSON_THROW_ON_ERROR)
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'Database error on app server'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfReturnedSecretMatchesTheOldOne(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $this->testApp->setAppSecret('4pp-s3cr3t');

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn('proof');

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => 'proof',
                    'secret' => $this->testApp->getAppSecret(),
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'The new app secret returned from the App must be different from the current one.'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testSuccessfullyRegisters(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn('proof');

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => 'proof',
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );
        $this->mockHandler->append(new Response());

        $appRepositoryMock = $this->createMock(EntityRepository::class);
        $appRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'app',
                1,
                new AppCollection([$this->testApp]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        // Two-phase commit: the new secret is saved as unconfirmed before the confirm call, then promoted
        // to app_secret and the unconfirmed fields cleared once the app accepts it.
        $update = $this->exactly(2);
        $appRepositoryMock->expects($update)
            ->method('update')
            ->willReturnCallback(function (array $payload) use ($update): EntityWrittenContainerEvent {
                if ($update->numberOfInvocations() === 1) {
                    static::assertSame($this->testApp->getId(), $payload[0]['id']);
                    static::assertSame(['4pp-s3cr3t'], $payload[0]['unconfirmedAppSecrets']);
                } else {
                    static::assertSame(
                        [[
                            'id' => $this->testApp->getId(),
                            'appSecret' => '4pp-s3cr3t',
                            'unconfirmedAppSecrets' => null,
                        ]],
                        $payload
                    );
                }

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $appRegistrationService = $this->createService($appRepositoryMock);
        $appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesInvalidJson(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(new Response(body: '{invalid-json: test,}'));

        $this->expectExceptionObject(AppException::registrationFailed('test', 'JSON response could not be decoded'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionWithStatusCodeAndResponseBody(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $responseBody = json_encode(['some' => 'data', 'without' => 'error field'], \JSON_THROW_ON_ERROR);

        $this->mockHandler->append(
            new RequestException('Unknown app', $registrationRequest, new Response(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR, body: $responseBody)),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'Got status code 500, with response: ' . $responseBody));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesNoProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn(Uuid::randomHex());

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => 1337,
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'The app server provided no proof'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfAppServerProvidesInvalidProof(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn(Uuid::randomHex());

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => Uuid::randomHex(),
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );

        $this->expectExceptionObject(AppException::registrationFailed('test', 'The app server provided an invalid proof'));

        $this->appRegistrationService->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testKeepsPendingSecretWhenHandshakeFailsWithClientError(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        // A 4xx on the handshake is not the app rejecting a confirm: it must not drop a pending secret
        // left behind by an earlier rotation that ended without a clear answer.
        $this->mockHandler->append(
            new ClientException('app rejected the handshake', $registrationRequest, new Response(SymfonyResponse::HTTP_FORBIDDEN)),
        );

        $appRepositoryMock = $this->createMock(EntityRepository::class);
        $appRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'app',
                1,
                new AppCollection([$this->testApp]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        $appRepositoryMock->expects($this->never())->method('update');

        $this->expectException(AppException::class);

        $this->createService($appRepositoryMock)
            ->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testDoesNotPersistUnconfirmedSecretWhenConfirmationPayloadCannotBeBuilt(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($handshake->assembleRequest());
        $handshakeMock->method('fetchAppProof')->willReturn('proof');

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_OK,
                body: json_encode([
                    'proof' => 'proof',
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );

        // A failure while building the confirm payload happens before any confirm is sent: no unconfirmed
        // record may be written for a secret the app was never asked to confirm.
        $shopIdProviderMock = static::createStub(ShopIdProvider::class);
        $shopIdProviderMock->method('getShopId')->willThrowException(new \RuntimeException('shop id backend failed'));

        $appRepositoryMock = $this->createMock(EntityRepository::class);
        $appRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'app',
                1,
                new AppCollection([$this->testApp]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        $appRepositoryMock->expects($this->never())->method('update');

        $this->expectExceptionObject(new \RuntimeException('shop id backend failed'));

        $this->createService($appRepositoryMock, $shopIdProviderMock)
            ->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testMintingASixthUnconfirmedSecretEvictsTheNewestOldMintAndKeepsTheOldest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        // old-1 is the newest previous mint, old-5 the oldest — the secret the app most likely still holds.
        $this->testApp->setUnconfirmedAppSecrets(['old-1', 'old-2', 'old-3', 'old-4', 'old-5']);

        $handshake = new PrivateHandshake(
            'https://shopware.swag',
            's3cr3t',
            'https://app.server/register',
            'test',
            'shop-id',
            '6.5.2.0',
            new NativeClock()
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = static::createStub(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn('proof');

        $this->handshakeFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($handshakeMock);

        $this->mockHandler->append(
            new Response(
                SymfonyResponse::HTTP_OK,
                body: json_encode([
                    'proof' => 'proof',
                    'secret' => 'new-secret',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        );
        // The confirm fails without a clear answer, so the capped unconfirmed list is the only write.
        $this->mockHandler->append(new RequestException('confirm timed out', $registrationRequest));

        $appRepositoryMock = $this->createMock(EntityRepository::class);
        $appRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'app',
                1,
                new AppCollection([$this->testApp]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );
        $appRepositoryMock->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $payload): EntityWrittenContainerEvent {
                static::assertSame(
                    ['new-secret', 'old-1', 'old-2', 'old-3', 'old-5'],
                    $payload[0]['unconfirmedAppSecrets']
                );

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $this->expectException(AppException::class);

        $this->createService($appRepositoryMock)
            ->registerApp($manifest, $this->testApp->getId(), 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    private function createService(EntityRepository $appRepository, ?ShopIdProvider $shopIdProvider = null): AppRegistrationService
    {
        if ($shopIdProvider === null) {
            $shopIdProvider = static::createStub(ShopIdProvider::class);
            $shopIdProvider->method('getShopId')->willReturn(ShopId::v2('shop-id'));
        }

        return new AppRegistrationService(
            $this->handshakeFactoryMock,
            new Client(['handler' => $this->mockHandler]),
            $appRepository,
            'https://shopware.swag',
            $shopIdProvider,
            '6.5.2.0',
            new NativeClock(),
            new NullLogger(),
        );
    }

    private function createAppEntity(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('test');

        $integration = new IntegrationEntity();
        $integration->setId(Uuid::randomHex());
        $integration->setLabel('test-integration');
        $integration->setAccessKey('test-access-key');
        $integration->setSecretAccessKey('test-secret-key');

        $app->setIntegration($integration);

        return $app;
    }
}
