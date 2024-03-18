<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\Registration\PrivateHandshake;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AppRegistrationService::class)]
class AppRegistrationServiceTest extends TestCase
{
    public function testDoesNotRegisterAtAppServerIfManifestHasNoSetup(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest_no_setup.xml');

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::never())->method('create');

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory);
        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    public function testThrowsAppRegistrationExceptionIfStoreHandshakeFails(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifest.xml');

        $handshake = new StoreHandshake(
            'https://shopware.swag',
            'http://app.server/register',
            'test',
            'shop-id',
            $this->createMock(StoreClient::class),
            '6.5.2.0',
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(StoreHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([
            new RequestException('Unknown app', $registrationRequest),
        ]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: Unknown app');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
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
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([
            new RequestException(
                '',
                $registrationRequest,
                new Response(
                    SymfonyResponse::HTTP_BAD_REQUEST,
                    body: json_encode(['error' => 'Database error on app server'], \JSON_THROW_ON_ERROR)
                )
            ),
        ]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: Database error on app server');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
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
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode(['error' => 'Database error on app server'], \JSON_THROW_ON_ERROR)
            ),
        ]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: Database error on app server');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
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
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([new Response(body: '{invalid-json: test,}')]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: JSON response could not be decoded');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
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
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn(Uuid::randomHex());

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => 1337,
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        ]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: The app server provided no proof');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
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
        );

        $registrationRequest = $handshake->assembleRequest();

        $handshakeMock = $this->createMock(PrivateHandshake::class);
        $handshakeMock->method('assembleRequest')->willReturn($registrationRequest);
        $handshakeMock->method('fetchAppProof')->willReturn(Uuid::randomHex());

        $handshakeFactory = $this->createMock(HandshakeFactory::class);
        $handshakeFactory->expects(static::once())
            ->method('create')
            ->willReturn($handshakeMock);

        $httpClient = $this->createHttpClient([
            new Response(
                SymfonyResponse::HTTP_BAD_REQUEST,
                body: json_encode([
                    'proof' => Uuid::randomHex(),
                    'secret' => '4pp-s3cr3t',
                    'confirmation_url' => 'https://app.server/confirm',
                ], \JSON_THROW_ON_ERROR)
            ),
        ]);

        $appRegistrationService = $this->createAppRegistrationService($handshakeFactory, $httpClient);

        $this->expectException(AppRegistrationException::class);
        $this->expectExceptionMessage('App registration for "test" failed: The app server provided an invalid proof');

        $appRegistrationService->registerApp($manifest, 'id', 's3cr3t-4cc3s-k3y', Context::createDefaultContext());
    }

    /**
     * @param (HandshakeFactory&MockObject)|null $handshakeFactory
     */
    private function createAppRegistrationService(
        ?HandshakeFactory $handshakeFactory = null,
        ?Client $httpClient = null,
    ): AppRegistrationService {
        return new AppRegistrationService(
            $handshakeFactory ?? $this->createMock(HandshakeFactory::class),
            $httpClient ?? new Client(),
            $this->createMock(EntityRepository::class),
            'https://shopware.swag',
            $this->createMock(ShopIdProvider::class),
            '6.5.2.0'
        );
    }

    /**
     * @param array<Response|RequestException> $responses
     */
    private function createHttpClient(array $responses): Client
    {
        $mockHandler = new MockHandler($responses);

        return new Client(['handler' => $mockHandler]);
    }
}
