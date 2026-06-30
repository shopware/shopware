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
    /**
     * A freshly generated secret/access-key is base64url-encoded (see AccessKeyHelper): the `+/=` of standard
     * base64 are mapped to `-_` and stripped. Matching this charset proves the value handed to the app server
     * is a newly minted secret, not the stale one carried over from the previous integration.
     */
    private const FRESHLY_GENERATED_SECRET = '/^[A-Za-z0-9_-]+$/';

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
        // A fixed clock so the soft-delete timestamp written for a retired integration is deterministic.
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

        // the app id resolves to no entity, so loadApp throws before anything is rotated
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
        $oldIntegrationId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, $oldIntegrationId);
        $this->setupAppLookup($appId, $app);

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
                static::matchesRegularExpression(self::FRESHLY_GENERATED_SECRET),
                $context
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
        $this->integrationRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($oldIntegrationId): bool {
                return $data[0]['id'] === $oldIntegrationId
                    && $data[0]['deletedAt'] instanceof \DateTimeImmutable
                    && $data[0]['deletedAt']->format(\DateTimeInterface::ATOM) === $this->clock->now()->format(\DateTimeInterface::ATOM);
            }), static::isInstanceOf(Context::class));

        // one log when rotation starts, one when it completes
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
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        // a previous rotation left an unresolved pending secret
        $app->setUnconfirmedAppSecrets(['left-over-pending']);
        $this->setupAppLookup($appId, $app);

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
        $context = Context::createDefaultContext();

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $app->setAppSecret('committed-secret');
        $this->setupAppLookup($appId, $app);

        $this->registrationLock->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        $this->setupResolvableManifestWithSetup();

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
        $context = Context::createDefaultContext();

        $secretAppStillTrusts = 'the-secret-the-app-still-trusts';

        $app = $this->createAppOnIntegration($appId, Uuid::randomHex());
        $app->setUnconfirmedAppSecrets(['minted-by-the-prior-recovery', $secretAppStillTrusts]);
        $this->setupAppLookup($appId, $app);

        $this->registrationLock->method('acquire')->willReturn($this->lock);
        $this->lock->expects($this->once())->method('release');

        $this->setupResolvableManifestWithSetup();

        // the app rejects the freshly minted pending but accepts the remembered candidate
        $triedSecrets = [];
        $this->registrationService->method('reRegisterWithAppHeldSecret')
            ->willReturnCallback(function (Manifest $manifest, string $id, string $secretAccessKey, Context $context, string $appHeldSecret) use (&$triedSecrets, $secretAppStillTrusts): void {
                $triedSecrets[] = $appHeldSecret;
                if ($appHeldSecret !== $secretAppStillTrusts) {
                    throw AppException::appRegistrationRejected('TestApp', 'the app does not trust this secret');
                }
            });

        $this->service->recoverNow($appId, $context);

        static::assertContains($secretAppStillTrusts, $triedSecrets, 'recovery must try the secret remembered from a prior ambiguous attempt');
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
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with($appId)->willReturn($app);

        $this->appRepository->method('search')->willReturn($searchResult);
    }

    /**
     * Wires the source resolver and manifest factory to return a manifest that still declares <setup>, which
     * recovery requires before it will re-register. The manifest/filesystem paths are irrelevant here, so they
     * are left unconstrained.
     */
    private function setupResolvableManifestWithSetup(): void
    {
        $manifest = $this->createMock(Manifest::class);
        $manifest->method('getSetup')->willReturn(Setup::fromArray(['registrationUrl' => 'https://example.com/register']));

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('hasFile')->willReturn(false);
        $filesystem->method('path')->willReturn('/path/to/manifest.xml');

        $this->sourceResolver->method('filesystemForApp')->willReturn($filesystem);
        $this->manifestFactory->method('createFromXmlFile')->willReturn($manifest);
    }
}
