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
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
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

    private SourceResolver&Stub $sourceResolver;

    private MessageBusInterface&Stub $messageBus;

    private LoggerInterface&MockObject $logger;

    private ManifestFactory&Stub $manifestFactory;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->registrationService = static::createStub(AppRegistrationService::class);
        $this->appRepository = $this->createMock(EntityRepository::class);
        $this->integrationRepository = static::createStub(EntityRepository::class);
        $this->sourceResolver = static::createStub(SourceResolver::class);
        $this->messageBus = static::createStub(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manifestFactory = static::createStub(ManifestFactory::class);
        $this->clock = new MockClock('2025-06-13 12:00:00');

        $this->service = new AppSecretRotationService(
            $this->registrationService,
            $this->appRepository,
            $this->integrationRepository,
            $this->sourceResolver,
            $this->messageBus,
            $this->logger,
            $this->manifestFactory,
            $this->clock
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

        $this->appRepository->expects($this->never())
            ->method('search');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (RotateAppSecretMessage $message) use ($appId) {
                return $message->getAppId() === $appId
                    && $message->getTrigger() === AppSecretRotationService::TRIGGER_API;
            }))
            ->willReturn(new Envelope(new RotateAppSecretMessage($appId, AppSecretRotationService::TRIGGER_API)));

        $service = $this->createService(
            $this->registrationService,
            $this->integrationRepository,
            $this->sourceResolver,
            $messageBus,
            $this->manifestFactory
        );

        $service->scheduleRotation($app, AppSecretRotationService::TRIGGER_API);
    }

    public function testRotateNowThrowsExceptionWhenAppNotFound(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new AppCollection());

        $this->appRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects($this->never())
            ->method('info');

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

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new AppCollection([$app]));

        $this->appRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $manifest = static::createStub(Manifest::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('path')
            ->with('manifest.xml')
            ->willReturn('/path/to/manifest.xml');

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForApp')
            ->with($app)
            ->willReturn($filesystem);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/path/to/manifest.xml')
            ->willReturn($manifest);

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('registerApp')
            ->with(
                $manifest,
                $appId,
                static::matchesRegularExpression('/^[A-Za-z0-9_-]+$/'),
                $context
            );

        $this->appRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($appId) {
                return $data[0]['id'] === $appId
                    && isset($data[0]['integration'])
                    && isset($data[0]['integration']['label'])
                    && isset($data[0]['integration']['accessKey'])
                    && isset($data[0]['integration']['secretAccessKey']);
            }), static::isInstanceOf(Context::class));

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use ($integrationId) {
                return $data[0]['id'] === $integrationId
                    && $data[0]['deletedAt'] instanceof \DateTimeImmutable
                    && $data[0]['deletedAt']->format(\DateTimeInterface::ATOM) === $this->clock->now()->format(\DateTimeInterface::ATOM);
            }), static::isInstanceOf(Context::class));

        $this->logger->expects($this->exactly(2))
            ->method('info');

        $service = $this->createService(
            $registrationService,
            $integrationRepository,
            $sourceResolver,
            $this->messageBus,
            $manifestFactory
        );

        $service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    public function testRotateNowLogsErrorOnFailure(): void
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

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new AppCollection([$app]));

        $this->appRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $manifest = static::createStub(Manifest::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('path')
            ->with('manifest.xml')
            ->willReturn('/path/to/manifest.xml');

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForApp')
            ->with($app)
            ->willReturn($filesystem);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/path/to/manifest.xml')
            ->willReturn($manifest);

        $exception = new \RuntimeException('Registration failed');
        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('registerApp')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Starting app secret rotation', static::anything());

        $this->logger->expects($this->once())
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

        $this->expectExceptionObject(new \RuntimeException('Registration failed'));

        $service = $this->createService(
            $registrationService,
            $this->integrationRepository,
            $sourceResolver,
            $this->messageBus,
            $manifestFactory
        );

        $service->rotateNow($appId, $context, AppSecretRotationService::TRIGGER_CLI);
    }

    /**
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    private function createService(
        AppRegistrationService $registrationService,
        EntityRepository $integrationRepository,
        SourceResolver $sourceResolver,
        MessageBusInterface $messageBus,
        ManifestFactory $manifestFactory
    ): AppSecretRotationService {
        return new AppSecretRotationService(
            $registrationService,
            $this->appRepository,
            $integrationRepository,
            $sourceResolver,
            $messageBus,
            $this->logger,
            $manifestFactory,
            $this->clock
        );
    }
}
