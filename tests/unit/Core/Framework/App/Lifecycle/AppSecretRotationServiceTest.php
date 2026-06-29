<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppRegistrationLock;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSecretRotationService::class)]
class AppSecretRotationServiceTest extends TestCase
{
    private AppSecretRotationService $service;

    private AppRegistrationService&MockObject $registrationService;

    /**
     * @var EntityRepository<AppCollection>&MockObject
     */
    private EntityRepository&MockObject $appRepository;

    /**
     * @var EntityRepository<IntegrationCollection>&MockObject
     */
    private EntityRepository&MockObject $integrationRepository;

    private SourceResolver&MockObject $sourceResolver;

    private MessageBusInterface&MockObject $messageBus;

    private LoggerInterface&MockObject $logger;

    private ManifestFactory&MockObject $manifestFactory;

    private MockClock $clock;

    private AppRegistrationLock&MockObject $registrationLock;

    private LockInterface&MockObject $lock;

    private Meter&MockObject $meter;

    protected function setUp(): void
    {
        $this->registrationService = $this->createMock(AppRegistrationService::class);
        $this->appRepository = $this->createMock(EntityRepository::class);
        $this->integrationRepository = $this->createMock(EntityRepository::class);
        $this->sourceResolver = $this->createMock(SourceResolver::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manifestFactory = $this->createMock(ManifestFactory::class);
        $this->clock = new MockClock('2025-06-13 12:00:00');
        $this->registrationLock = $this->createMock(AppRegistrationLock::class);
        $this->lock = $this->createMock(LockInterface::class);
        $this->meter = $this->createMock(Meter::class);

        $this->service = new AppSecretRotationService(
            $this->registrationService,
            $this->appRepository,
            $this->integrationRepository,
            $this->sourceResolver,
            $this->messageBus,
            $this->logger,
            $this->manifestFactory,
            $this->clock,
            $this->registrationLock,
            $this->meter
        );
    }

    public function testScheduleRotationDispatchesMessage(): void
    {
        $appId = Uuid::randomHex();
        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Scheduling app secret rotation',
                [
                    'appId' => $appId,
                    'appName' => 'TestApp',
                    'trigger' => AppSecretRotationService::TRIGGER_API,
                ]
            );

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (RotateAppSecretMessage $message) use ($appId) {
                return $message->getAppId() === $appId
                    && $message->getTrigger() === AppSecretRotationService::TRIGGER_API;
            }))
            ->willReturn(new Envelope(new RotateAppSecretMessage($appId, AppSecretRotationService::TRIGGER_API)));

        $this->service->scheduleRotation($app, AppSecretRotationService::TRIGGER_API);
    }

    public function testRotateNowThrowsExceptionWhenAppNotFound(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->registrationLock->method('acquire')->willReturn($this->lock);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->expects($this->once())
            ->method('get')
            ->with($appId)
            ->willReturn(null);

        $this->appRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->expectException(AppException::class);

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowSuccessfullyRotatesSecret(): void
    {
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $integration = new IntegrationEntity();
        $integration->setId($integrationId);
        $integration->setLabel('TestApp Integration');
        $integration->setAccessKey('old-access-key');
        $integration->setSecretAccessKey('old-secret-key');

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        $app->setIntegrationId($integrationId);
        $app->setIntegration($integration);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with($appId)->willReturn($app);
        $this->appRepository->method('search')->willReturn($searchResult);

        // the per-app lock is taken for the whole rotation and released afterwards
        $this->registrationLock->expects($this->once())->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        $manifest = $this->createMock(Manifest::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('path')->with('manifest.xml')->willReturn('/path/to/manifest.xml');
        $this->sourceResolver->method('filesystemForApp')->with($app)->willReturn($filesystem);
        $this->manifestFactory->method('createFromXmlFile')->with('/path/to/manifest.xml')->willReturn($manifest);

        $this->registrationService->expects($this->once())
            ->method('registerApp')
            ->with(
                $manifest,
                $appId,
                static::matchesRegularExpression('/^[A-Za-z0-9_-]+$/'),
                $context
            );

        // the app is moved onto a freshly minted integration (created via the nested write)...
        $this->appRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($appId, $integrationId): bool {
                return $data[0]['id'] === $appId
                    && isset($data[0]['integration']['id'], $data[0]['integration']['label'], $data[0]['integration']['accessKey'], $data[0]['integration']['secretAccessKey'])
                    && $data[0]['integration']['id'] !== $integrationId;
            }), static::isInstanceOf(Context::class));

        // ...and the old integration is retired
        $this->integrationRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($integrationId): bool {
                return $data[0]['id'] === $integrationId
                    && $data[0]['deletedAt'] instanceof \DateTimeImmutable
                    && $data[0]['deletedAt']->format(\DateTimeInterface::ATOM) === $this->clock->now()->format(\DateTimeInterface::ATOM);
            }), static::isInstanceOf(Context::class));

        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowAbortsWhenAnotherOperationHoldsTheLock(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // another rotation or recovery for this app is already running, so the lock cannot be taken
        $this->registrationLock->expects($this->once())->method('acquire')->willThrowException(AppException::appSecretRotationInProgress($appId));
        $this->lock->expects($this->never())->method('release');

        // we never even read the app or call the app server when we cannot take the lock
        $this->appRepository->expects($this->never())->method('search');
        $this->registrationService->expects($this->never())->method('registerApp');

        $this->expectExceptionObject(AppException::appSecretRotationInProgress($appId));

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowAbortsWhenAPendingSecretIsUnresolved(): void
    {
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $integration = new IntegrationEntity();
        $integration->setId($integrationId);
        $integration->setLabel('TestApp Integration');

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        $app->setIntegrationId($integrationId);
        $app->setIntegration($integration);
        // a previous rotation left an unresolved pending secret
        $app->setUnconfirmedAppSecrets(['left-over-pending']);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with($appId)->willReturn($app);
        $this->appRepository->method('search')->willReturn($searchResult);

        // the lock is taken, and released again even though we abort
        $this->registrationLock->expects($this->once())->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        // we must not rotate over the unresolved pending secret
        $this->registrationService->expects($this->never())->method('registerApp');

        $this->expectExceptionObject(AppException::appSecretRotationAlreadyPending('TestApp'));

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRecoverRevertsTheIntegrationWhenAnAttemptThrowsANonAppException(): void
    {
        // A failure that is NOT an app rejection — a malformed handshake response (TypeError), a DAL write
        // error — thrown after the integration switch must still revert the fresh integration, not leave the
        // app orphaned on it. Mirrors rotateNow's \Throwable handling; an outer catch of only AppException
        // would let this escape with the new integration current and the old one soft-deleted.
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $integration = new IntegrationEntity();
        $integration->setId($integrationId);
        $integration->setLabel('TestApp Integration');

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        $app->setIntegrationId($integrationId);
        $app->setIntegration($integration);
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $app->setAppSecret('committed-secret');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with($appId)->willReturn($app);
        $this->appRepository->method('search')->willReturn($searchResult);

        $this->registrationLock->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getSetup')->willReturn(Setup::fromArray(['registrationUrl' => 'https://example.com/register']));
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('hasFile')->willReturn(false);
        $filesystem->method('path')->willReturn('/path/to/manifest.xml');
        $this->sourceResolver->method('filesystemForApp')->willReturn($filesystem);
        $this->manifestFactory->method('createFromXmlFile')->willReturn($manifest);

        // the recovery re-registration blows up with a non-AppException
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willThrowException(new \RuntimeException('malformed handshake response'));

        // two integration writes: the switch onto a fresh integration, then the revert that retires it again.
        // Without the \Throwable catch the revert never runs and this is called only once.
        $this->integrationRepository->expects($this->exactly(2))->method('update');

        $this->expectException(\RuntimeException::class);

        $this->service->recoverNow($appId, $context);
    }

    public function testRecoverTriesASecretRememberedFromAPriorAmbiguousAttempt(): void
    {
        // C1: an earlier ambiguous recovery prepended a freshly minted secret the app never promoted, leaving
        // the secret the app still trusts behind it in the pending list. Recovery must try every entry rather
        // than give up on the head alone and report the app "claimed".
        $appId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $integration = new IntegrationEntity();
        $integration->setId($integrationId);
        $integration->setLabel('TestApp Integration');

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        $app->setIntegrationId($integrationId);
        $app->setIntegration($integration);
        $app->setUnconfirmedAppSecrets(['minted-by-the-prior-recovery', 'the-secret-the-app-still-trusts']);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with($appId)->willReturn($app);
        $this->appRepository->method('search')->willReturn($searchResult);

        $this->registrationLock->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getSetup')->willReturn(Setup::fromArray(['registrationUrl' => 'https://example.com/register']));
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('hasFile')->willReturn(false);
        $filesystem->method('path')->willReturn('/path/to/manifest.xml');
        $this->sourceResolver->method('filesystemForApp')->willReturn($filesystem);
        $this->manifestFactory->method('createFromXmlFile')->willReturn($manifest);

        // the app rejects the freshly minted pending but accepts the remembered candidate
        $tried = [];
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willReturnCallback(function (Manifest $manifest, string $id, string $secretAccessKey, Context $context, string $appHeldSecret) use (&$tried): void {
                $tried[] = $appHeldSecret;
                if ($appHeldSecret !== 'the-secret-the-app-still-trusts') {
                    throw AppException::appRegistrationRejected('TestApp', 'the app does not trust this secret');
                }
            });

        $this->service->recoverNow($appId, $context);

        static::assertContains('the-secret-the-app-still-trusts', $tried, 'recovery must try the secret remembered from a prior ambiguous attempt');
    }
}
