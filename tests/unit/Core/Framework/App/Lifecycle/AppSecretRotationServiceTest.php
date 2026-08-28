<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\Clock\MockClock;
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

    private AppRegistrationService&Stub $registrationService;

    /**
     * @var EntityRepository<AppCollection>&MockObject
     */
    private EntityRepository&MockObject $appRepository;

    /**
     * @var EntityRepository<IntegrationCollection>&Stub
     */
    private EntityRepository&Stub $integrationRepository;

    private MessageBusInterface&Stub $messageBus;

    private LoggerInterface&Stub $logger;

    private ManifestFactory&MockObject $manifestFactory;

    private DeletedAppsGateway&Stub $deletedAppsGateway;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->registrationService = static::createStub(AppRegistrationService::class);
        $this->appRepository = $this->createMock(EntityRepository::class);
        $this->integrationRepository = static::createStub(EntityRepository::class);
        $this->messageBus = static::createStub(MessageBusInterface::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->manifestFactory = $this->createMock(ManifestFactory::class);
        $this->deletedAppsGateway = static::createStub(DeletedAppsGateway::class);
        // A fixed clock so the soft-delete timestamp written for a retired integration is deterministic.
        $this->clock = new MockClock('2025-06-13 12:00:00');
        $this->service = $this->createService();
    }

    public function testScheduleRotationDispatchesMessage(): void
    {
        $appId = Uuid::randomHex();
        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        // A pending unconfirmed secret does not block scheduling: the handler's rotateNow reconciles it
        // instead of rotating over it, so the rotation is queued like for any other app.
        $app->setUnconfirmedAppSecrets(['left-over-pending']);

        $this->appRepository->expects($this->never())
            ->method('search');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Scheduling app secret rotation',
                [
                    'appId' => $appId,
                    'appName' => 'TestApp',
                    'trigger' => AppSecretRotationService::TRIGGER_API,
                ]
            );

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (RotateAppSecretMessage $message) use ($appId) {
                return $message->getAppId() === $appId
                    && $message->getTrigger() === AppSecretRotationService::TRIGGER_API;
            }))
            ->willReturn(new Envelope(new RotateAppSecretMessage($appId, AppSecretRotationService::TRIGGER_API)));

        $this->createService(messageBus: $messageBus, logger: $logger)
            ->scheduleRotation($app, AppSecretRotationService::TRIGGER_API);
    }

    public function testRotateNowThrowsExceptionWhenAppNotFound(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // the app id resolves to no entity, so loadApp throws before anything is rotated
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new AppCollection());
        $this->appRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $this->expectException(AppException::class);

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowSuccessfullyRotatesSecret(): void
    {
        $appId = Uuid::randomHex();
        $oldIntegrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, $oldIntegrationId);
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $manifest = static::createStub(Manifest::class);
        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn($manifest);

        // a clean app is one candidate attempt: the handshake signed with its committed secret
        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('reRegisterWithAppHeldSecret')
            ->with(
                $manifest,
                $appId,
                static::matchesRegularExpression('/^[A-Za-z0-9_-]+$/'),
                $context,
                'committed-secret'
            );

        // the app is moved onto a freshly minted integration (created via the nested write)...
        $this->appRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($appId, $oldIntegrationId): bool {
                return $data[0]['id'] === $appId
                    && isset($data[0]['integration']['id'], $data[0]['integration']['label'], $data[0]['integration']['accessKey'], $data[0]['integration']['secretAccessKey'])
                    && $data[0]['integration']['id'] !== $oldIntegrationId;
            }), static::isInstanceOf(Context::class));

        // ...and the old integration is retired with the current (fixed-clock) timestamp
        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($oldIntegrationId): bool {
                return $data[0]['id'] === $oldIntegrationId
                    && $data[0]['deletedAt'] instanceof \DateTimeImmutable
                    && $data[0]['deletedAt']->format(\DateTimeInterface::ATOM) === $this->clock->now()->format(\DateTimeInterface::ATOM);
            }), static::isInstanceOf(Context::class));

        // one log when rotation starts, one when it completes
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info');

        $this->createService(registrationService: $registrationService, integrationRepository: $integrationRepository, logger: $logger)
            ->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowLogsErrorOnFailure(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $manifest = static::createStub(Manifest::class);
        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn($manifest);

        $exception = new \RuntimeException('Registration failed');
        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('reRegisterWithAppHeldSecret')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Starting app secret rotation', static::anything());
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'App secret rotation failed',
                [
                    'appId' => $appId,
                    'appName' => 'TestApp',
                    'trigger' => AppSecretRotationService::TRIGGER_CLI,
                    'error' => 'Registration failed',
                ]
            );

        $this->expectExceptionObject($exception);

        $this->createService(registrationService: $registrationService, logger: $logger)
            ->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowRecoversAPendingSecretInsteadOfRotatingOverIt(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        // a previous rotation left an unresolved pending secret
        $app->setUnconfirmedAppSecrets(['left-over-pending']);
        $this->setupAppLookup($appId, $app);

        $this->setupResolvableManifest();

        // re-running the rotation reconciles the pending secret: the handshake is signed with the app-held
        // candidate left behind by the interrupted attempt, not with a committed secret it never had.
        $tried = [];
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willReturnCallback(function (Manifest $manifest, string $id, string $secretAccessKey, Context $context, string $appHeldSecret) use (&$tried): void {
                $tried[] = $appHeldSecret;
            });

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);

        static::assertSame(['left-over-pending'], $tried, 'a pending secret must be recovered, not rotated over');
    }

    public function testRotateNowRevertsTheIntegrationWhenAnAttemptThrowsANonAppException(): void
    {
        // A failure that is NOT an app rejection — a malformed handshake response (TypeError), a DAL write
        // error — thrown after the integration switch must still revert the fresh integration when no confirm
        // could have delivered its credentials (no unconfirmed secret was saved). An outer catch of only
        // AppException would let this escape with the new integration current and the old one soft-deleted.
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $this->setupResolvableManifest();

        // the re-registration blows up with a non-AppException before any confirm was sent
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willThrowException(new \RuntimeException('malformed handshake response'));

        // two integration writes: the switch onto a fresh integration, then the revert that retires it again.
        // Without the \Throwable catch the revert never runs and this is called only once.
        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects($this->exactly(2))->method('update');

        $this->expectException(\RuntimeException::class);

        $this->createService(integrationRepository: $integrationRepository)
            ->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testAmbiguousFailureKeepsTheFreshIntegrationWhenAPendingSecretExists(): void
    {
        // The counterpart: an ambiguous failure *after this attempt stored a minted secret* means a confirm
        // may have delivered the fresh integration's credentials — keep it, so a later attempt can re-register
        // against it, and leave the pending list for that retry to sign with.
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $this->setupResolvableManifest();

        // The registration stores the minted secret before it sends the confirm, so a grown unconfirmed list is
        // what marks the confirm as having gone out.
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willReturnCallback(function () use ($app): void {
                $app->setUnconfirmedAppSecrets(['minted-before-the-confirm', 'pending-secret']);

                throw new \RuntimeException('confirm timed out');
            });

        // one integration write only: the switch onto the fresh integration — no revert
        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects($this->once())->method('update');

        $this->expectException(\RuntimeException::class);

        $this->createService(integrationRepository: $integrationRepository)
            ->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRecoverTriesASecretRememberedFromAPriorAmbiguousAttempt(): void
    {
        // C1: an earlier ambiguous recovery prepended a freshly minted secret the app never promoted, leaving
        // the secret the app still trusts behind it in the pending list. Recovery must try every entry rather
        // than give up on the head alone and report the app "claimed".
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $secretAppStillTrusts = 'the-secret-the-app-still-trusts';

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setUnconfirmedAppSecrets(['minted-by-the-prior-recovery', $secretAppStillTrusts]);
        $this->setupAppLookup($appId, $app);

        $this->setupResolvableManifest();

        // the app rejects the freshly minted pending but accepts the remembered candidate
        $triedSecrets = [];
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willReturnCallback(function (Manifest $manifest, string $id, string $secretAccessKey, Context $context, string $appHeldSecret) use (&$triedSecrets, $secretAppStillTrusts): void {
                $triedSecrets[] = $appHeldSecret;
                if ($appHeldSecret !== $secretAppStillTrusts) {
                    throw AppException::appRegistrationRejected('TestApp', 'the app does not trust this secret');
                }
            });

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);

        static::assertContains($secretAppStillTrusts, $triedSecrets, 'recovery must try the secret remembered from a prior ambiguous attempt');
    }

    public function testRecoverKeepsUnconfirmedSecretsWhenEveryCandidateIsRejected(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $oldIntegrationId = Uuid::randomHex();
        $unconfirmed = ['pending-secret'];

        $app = $this->createAppOnIntegration($appId, $oldIntegrationId);
        $app->setUnconfirmedAppSecrets($unconfirmed);
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $this->setupResolvableManifest();

        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willThrowException(AppException::appRegistrationRejected('TestApp', 'the app does not trust this secret'));

        $update = $this->exactly(2);
        $this->appRepository->expects($update)
            ->method('update')
            ->willReturnCallback(function (array $payload) use ($update, $appId, $oldIntegrationId): EntityWrittenContainerEvent {
                if ($update->numberOfInvocations() === 1) {
                    // first write: the switch onto a fresh integration
                    static::assertArrayHasKey('integration', $payload[0]);
                } else {
                    // second write: the revert — pointing back at the old integration WITHOUT touching the
                    // unconfirmed list (its absence from the payload is the kept-list assertion)
                    static::assertSame([['id' => $appId, 'integrationId' => $oldIntegrationId]], $payload);
                }

                return static::createStub(EntityWrittenContainerEvent::class);
            });

        $this->expectExceptionObject(AppException::appSecretRecoveryFailed('TestApp'));

        $this->service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    /**
     * Builds the service under test. The doubles a given test needs to assert on (a real mock with
     * expectations) are passed in; everything else falls back to the shared setUp double.
     *
     * @param EntityRepository<IntegrationCollection>|null $integrationRepository
     */
    private function createService(
        ?AppRegistrationService $registrationService = null,
        ?EntityRepository $integrationRepository = null,
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
    ): AppSecretRotationService {
        return new AppSecretRotationService(
            $registrationService ?? $this->registrationService,
            $this->appRepository,
            $integrationRepository ?? $this->integrationRepository,
            $messageBus ?? $this->messageBus,
            $logger ?? $this->logger,
            $this->manifestFactory,
            $this->clock,
            $this->deletedAppsGateway,
        );
    }

    /**
     * Builds an installed app sitting on its integration — the shape both rotation and recovery start from.
     * Scenario-specific state (the unconfirmed list, the committed secret) is set by the caller.
     */
    private function createAppOnIntegration(string $appId, string $integrationId): AppEntity
    {
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);
        $integration->setLabel('TestApp Integration');

        $app = new AppEntity();
        $app->setId($appId);
        $app->setName('TestApp');
        $app->setIntegrationId($integrationId);
        $app->setIntegration($integration);

        return $app;
    }

    /**
     * Wires the app repository so that loading $appId yields $app (or, with null, resolves to "not found").
     */
    private function setupAppLookup(string $appId, ?AppEntity $app): void
    {
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new AppCollection($app ? [$app] : []));

        // an attempt re-reads the app to see what it left behind, so the lookup count is scenario-specific
        $this->appRepository->expects($this->atLeastOnce())->method('search')->willReturn($searchResult);
    }

    /**
     * Wires the manifest factory to resolve a manifest for the app under test.
     */
    private function setupResolvableManifest(): void
    {
        $manifest = static::createStub(Manifest::class);

        $this->manifestFactory->expects($this->once())->method('createFromApp')->willReturn($manifest);
    }
}
