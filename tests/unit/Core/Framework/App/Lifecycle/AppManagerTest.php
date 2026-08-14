<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\CompatibilityValidator;
use Shopware\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Util\Result;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppManager::class)]
class AppManagerTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private PermissionLifecycleService&MockObject $permissionLifecycle;

    private AppRegistrationService&MockObject $registrationService;

    private AppSecretRotationService&MockObject $appSecretRotationService;

    private ManifestFactory&MockObject $manifestFactory;

    private ActiveAppsLoader&MockObject $activeAppsLoader;

    private SystemConfigService&MockObject $systemConfigService;

    /**
     * @var StaticEntityRepository<IntegrationCollection>
     */
    private StaticEntityRepository $integrationRepository;

    private AssetService&MockObject $assetService;

    private ScriptExecutor&MockObject $scriptExecutor;

    private CustomEntityLifecycleService $customEntityLifecycleService;

    private SourceResolver $sourceResolver;

    private ConfigReader&MockObject $configReader;

    private ManifestValidator $manifestValidator;

    private DeletedAppsGateway $deletedAppsGateway;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->permissionLifecycle = $this->createMock(PermissionLifecycleService::class);
        $this->registrationService = $this->createMock(AppRegistrationService::class);
        $this->appSecretRotationService = $this->createMock(AppSecretRotationService::class);
        $this->manifestFactory = $this->createMock(ManifestFactory::class);
        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->integrationRepository = new StaticEntityRepository([]);
        $this->assetService = $this->createMock(AssetService::class);
        $this->scriptExecutor = $this->createMock(ScriptExecutor::class);
        $this->customEntityLifecycleService = $this->createDefaultCustomEntityLifecycleService();
        $this->sourceResolver = new StaticSourceResolver();
        $this->configReader = $this->createMock(ConfigReader::class);
        $this->manifestValidator = new ManifestValidator([]);
        $this->deletedAppsGateway = static::createStub(DeletedAppsGateway::class);
    }

    public function testInstallIsRefusedWhenARequiredValidatorReportsAnError(): void
    {
        $manifest = ManifestFixture::empty();

        $refusal = AppException::validationFailedFromError(new IncompatibleAppError('test'));

        $manifestValidator = static::createStub(ManifestValidator::class);
        $manifestValidator->method('validate')->willReturn(Result::failed([new IncompatibleAppError('test')]));
        $this->manifestValidator = $manifestValidator;

        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectExceptionObject($refusal);

        $this->createAppManager(AppFixture::createAppRepository())
            ->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testStrictValidationTurnsAnAdvisoryErrorIntoARefusal(): void
    {
        $manifestValidator = static::createStub(ManifestValidator::class);
        $manifestValidator->method('validate')->willReturn(Result::failed([new NotHookableError(['hook: future.event'])]));
        $this->manifestValidator = $manifestValidator;

        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectExceptionObject(AppException::validationFailedFromError(new NotHookableError(['hook: future.event'])));

        $this->createAppManager(AppFixture::createAppRepository())->install(
            ManifestFixture::empty(),
            new AppInstallParameters(strictValidation: true),
            Context::createDefaultContext()
        );
    }

    public function testStrictValidationTurnsAnAdvisoryErrorIntoARefusalOnUpdate(): void
    {
        $manifestValidator = static::createStub(ManifestValidator::class);
        $manifestValidator->method('validate')->willReturn(Result::failed([new NotHookableError(['hook: future.event'])]));
        $this->manifestValidator = $manifestValidator;

        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectExceptionObject(AppException::validationFailedFromError(new NotHookableError(['hook: future.event'])));

        $this->createAppManager(AppFixture::createAppRepository())->update(
            ManifestFixture::empty(),
            new AppUpdateParameters(strictValidation: true),
            AppFixture::createAppEntity(active: false),
            Context::createDefaultContext()
        );
    }

    public function testUpdateIsRefusedWhenARequiredValidatorReportsAnError(): void
    {
        $manifest = ManifestFixture::empty();

        $refusal = AppException::validationFailedFromError(new IncompatibleAppError('test'));

        $manifestValidator = static::createStub(ManifestValidator::class);
        $manifestValidator->method('validate')->willReturn(Result::failed([new IncompatibleAppError('test')]));
        $this->manifestValidator = $manifestValidator;

        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectExceptionObject($refusal);

        $this->createAppManager(AppFixture::createAppRepository())
            ->update($manifest, new AppUpdateParameters(), AppFixture::createAppEntity(active: false), Context::createDefaultContext());
    }

    public function testInstallThrowsIfAppAlreadyExists(): void
    {
        $existingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $appRepository = AppFixture::createAppRepository($existingApp);

        // an installed app without a pending secret is rejected outright, never sent into recovery
        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectExceptionObject(AppException::alreadyInstalled('test'));

        $this->createAppManager($appRepository)
            ->install(ManifestFixture::empty(), new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testInstallRollsBackAppDataWhenRegistrationFails(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $appRepository = AppFixture::createAppRepository();
        $installedApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $appRepository->addSearch(new AppCollection([$installedApp]));
        // The failure path re-reads the app to decide whether to roll back: with no pending secret (a clean
        // registration failure) it removes the app data.
        $appRepository->addSearch(new AppCollection([$installedApp]));

        $this->registrationService->expects($this->once())
            ->method('registerApp')
            ->willThrowException(AppException::registrationFailed('test', 'registration failed'));

        $this->integrationRepository = new StaticEntityRepository([]);
        $this->permissionLifecycle->expects($this->once())->method('removeRole');

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->once())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        try {
            $this->createAppManager($appRepository)
                ->install($manifest, new AppInstallParameters(), $context);
            static::fail('Expected app registration to fail');
        } catch (AppRegistrationException) {
            static::assertCount(1, $appRepository->getPayloads(StaticEntityRepository::UPSERT));
            static::assertSame([['id' => $installedApp->getId()]], $appRepository->getPayloads(StaticEntityRepository::DELETE));
            static::assertSame([['id' => 'integration-id']], $this->integrationRepository->getPayloads(StaticEntityRepository::DELETE));
        }
    }

    public function testInstallKeepsTheAppWhenRegistrationLeavesAPendingSecret(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $appRepository = AppFixture::createAppRepository();
        $installedApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $appRepository->addSearch(new AppCollection([$installedApp]));
        // The failure path re-reads the app: an ambiguous registration (5xx/timeout) left a pending secret the
        // app may already hold, so the app must be KEPT for a later app:install repair, not deleted.
        $pendingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $pendingApp->setUnconfirmedAppSecrets(['left-over-pending']);
        $appRepository->addSearch(new AppCollection([$pendingApp]));

        $this->registrationService->expects($this->once())
            ->method('registerApp')
            ->willThrowException(AppException::registrationFailed('test', 'ambiguous confirm'));

        $this->integrationRepository = new StaticEntityRepository([]);
        // removeAppData removes the ACL role; it must NOT run, because the app is kept for recovery.
        $this->permissionLifecycle->expects($this->never())->method('removeRole');

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->expectExceptionObject(AppException::registrationFailed('test', 'ambiguous confirm'));

        $this->createAppManager($appRepository)
            ->install($manifest, new AppInstallParameters(), $context);
    }

    public function testReinstallOfAnAppHoldingAnUncommittedSecretRecoversInOneRun(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();

        // No app row: the app was uninstalled mid-rotation, so the carried candidates make this a recovery.
        $appRepository = AppFixture::createAppRepository();
        $reinstalledApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $reinstalledApp->setAppSecret('committed-secret');
        $reinstalledApp->setUnconfirmedAppSecrets(['pending-secret']);
        $recoveredApp = clone $reinstalledApp;
        $recoveredApp->setAppSecret('recovered-secret');
        $recoveredApp->setUnconfirmedAppSecrets(null);

        $appRepository->addSearch(new AppCollection([$reinstalledApp]));
        $appRepository->addSearch(new AppCollection([$reinstalledApp]));
        $appRepository->addSearch(new AppCollection([$recoveredApp]));

        $this->deletedAppsGateway = $this->createMock(DeletedAppsGateway::class);
        $this->deletedAppsGateway->expects($this->exactly(2))
            ->method('getDeletedAppSecret')
            ->with('test')
            ->willReturn('committed-secret');
        $this->deletedAppsGateway->expects($this->exactly(2))
            ->method('getDeletedAppUnconfirmedSecrets')
            ->with('test')
            ->willReturn(['pending-secret']);

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with(static::isString(), $context, AppSecretRotationService::TRIGGER_RECOVERY);
        // A plain handshake would sign with the secret the app stopped trusting.
        $this->registrationService->expects($this->never())->method('registerApp');

        // the row is re-created from the manifest, which re-applies the app's permissions
        $this->permissionLifecycle->expects($this->once())->method('updatePrivileges');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->once())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->once())->method('copyAssetsFromApp');
        // the installed hook and the activated hook — installation resumes with activation on by default
        $this->scriptExecutor->expects($this->exactly(2))->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $handler = $this->createMock(AbstractLifecycleHandler::class);
        $handler->expects($this->once())->method('install');

        // Recovery re-creates the record, so the locale is resolved twice.
        $languageRepository = AppFixture::createLanguageRepository();
        $languageRepository->addSearch($languageRepository->searches[0]);

        $this->createAppManager($appRepository, persisters: [$handler], languageRepository: $languageRepository)
            ->install($manifest, new AppInstallParameters(), $context);

        $upserts = $appRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertSame(['pending-secret'], $upserts[0]['unconfirmedAppSecrets']);
        static::assertSame('committed-secret', $upserts[0]['appSecret']);
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
    }

    public function testInstallRepairsCompletedAppWithoutReplayingLifecycleOrActivation(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $app->setAppSecret('committed-secret');
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $appRepository = AppFixture::createAppRepository($app);
        $appRepository->addSearch(new AppCollection([$app]));

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($app->getId(), $context, AppSecretRotationService::TRIGGER_RECOVERY);

        $handler = $this->createMock(AbstractLifecycleHandler::class);
        $handler->expects($this->never())->method('install');
        $handler->expects($this->never())->method('activate');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->configReader->expects($this->never())->method('read');
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $manifestValidator = $this->createMock(ManifestValidator::class);
        $manifestValidator->expects($this->never())->method('validate');
        $this->manifestValidator = $manifestValidator;

        $this->createAppManager($appRepository, persisters: [$handler])->install(
            $manifest,
            new AppInstallParameters(activate: true),
            $context
        );

        static::assertFalse($app->isActive());
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
    }

    public function testInstallValidatesFreshHalfFinishedInstallBeforeRecovery(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);
        $pendingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $pendingApp->setAppSecret(null);
        $pendingApp->setUnconfirmedAppSecrets(['pending-secret']);

        $appRepository = AppFixture::createAppRepository($pendingApp);
        $appRepository->addSearch(new AppCollection([$pendingApp]));

        $this->manifestValidator = new ManifestValidator([new CompatibilityValidator('6.5.0.0')]);

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->expectExceptionObject(AppException::validationFailedFromError(new IncompatibleAppError('test')));

        $this->createAppManager($appRepository)->install(
            $manifest,
            new AppInstallParameters(),
            $context
        );
    }

    public function testInstallValidatesInterruptedReinstallBeforeRecovery(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $pendingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $pendingApp->setAppSecret('deleted-app-secret');
        $pendingApp->setUnconfirmedAppSecrets(['pending-secret']);

        $appRepository = AppFixture::createAppRepository($pendingApp);
        $appRepository->addSearch(new AppCollection([$pendingApp]));

        $this->deletedAppsGateway = $this->createMock(DeletedAppsGateway::class);
        $this->deletedAppsGateway->expects($this->once())
            ->method('getDeletedAppSecret')
            ->with('test')
            ->willReturn('deleted-app-secret');

        $refusal = AppException::validationFailedFromError(new IncompatibleAppError('test'));

        $manifestValidator = static::createStub(ManifestValidator::class);
        $manifestValidator->method('validate')->willReturn(Result::failed([new IncompatibleAppError('test')]));
        $this->manifestValidator = $manifestValidator;

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->expectExceptionObject($refusal);

        $this->createAppManager($appRepository)->install(
            $manifest,
            new AppInstallParameters(),
            $context
        );
    }

    public function testInstallResumesFreshHalfFinishedInstallAndHonoursActivation(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $pendingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $pendingApp->setUnconfirmedAppSecrets(['pending-secret']);
        $recoveredApp = clone $pendingApp;
        $recoveredApp->setAppSecret('recovered-secret');
        $recoveredApp->setUnconfirmedAppSecrets(null);

        $appRepository = AppFixture::createAppRepository($pendingApp);
        $appRepository->addSearch(new AppCollection([$pendingApp]), new AppCollection([$recoveredApp]));

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($pendingApp->getId(), $context, AppSecretRotationService::TRIGGER_RECOVERY);
        $this->registrationService->expects($this->never())->method('registerApp');

        $handler = $this->createMock(AbstractLifecycleHandler::class);
        $handler->expects($this->once())->method('install');
        $handler->expects($this->once())->method('activate');
        $this->scriptExecutor->expects($this->exactly(2))->method('execute');
        $this->activeAppsLoader->expects($this->once())->method('reset');
        $this->assetService->expects($this->once())
            ->method('copyAssetsFromApp')
            ->with('test', 'test');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->configReader->expects($this->never())->method('read');
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $this->createAppManager($appRepository, persisters: [$handler])->install(
            $manifest,
            new AppInstallParameters(activate: true),
            $context
        );

        static::assertTrue($recoveredApp->isActive());
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
    }

    public function testInstallResumesInterruptedReinstallMarkedByDeletedSecret(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $pendingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $pendingApp->setAppSecret('deleted-app-secret');
        $pendingApp->setUnconfirmedAppSecrets(['pending-secret']);
        $recoveredApp = clone $pendingApp;
        $recoveredApp->setAppSecret('recovered-secret');
        $recoveredApp->setUnconfirmedAppSecrets(null);

        $appRepository = AppFixture::createAppRepository($pendingApp);
        $appRepository->addSearch(new AppCollection([$pendingApp]), new AppCollection([$recoveredApp]));

        $this->deletedAppsGateway = $this->createMock(DeletedAppsGateway::class);
        $this->deletedAppsGateway->expects($this->once())
            ->method('getDeletedAppSecret')
            ->with('test')
            ->willReturn('deleted-app-secret');

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow');
        $this->registrationService->expects($this->never())->method('registerApp');

        $handler = $this->createMock(AbstractLifecycleHandler::class);
        $handler->expects($this->once())->method('install');
        $handler->expects($this->never())->method('activate');
        $this->scriptExecutor->expects($this->once())->method('execute');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->once())->method('copyAssetsFromApp');
        $this->configReader->expects($this->never())->method('read');
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $this->createAppManager($appRepository, persisters: [$handler])->install(
            $manifest,
            new AppInstallParameters(activate: false),
            $context
        );

        static::assertFalse($recoveredApp->isActive());
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
    }

    public function testInstallSurfacesAllRejectedRecoveryWithoutResumingLifecycle(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $app->setAppSecret('committed-secret');
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $appRepository = AppFixture::createAppRepository($app);
        $appRepository->addSearch(new AppCollection([$app]));

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->willThrowException(AppException::appSecretRecoveryFailed('test'));
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->configReader->expects($this->never())->method('read');
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $this->expectExceptionObject(AppException::appSecretRecoveryFailed('test'));

        $this->createAppManager($appRepository)->install(
            ManifestFixture::empty()->withSetup(),
            new AppInstallParameters(),
            $context
        );
    }

    public function testInstallPropagatesAmbiguousRecoveryForRetry(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $app->setAppSecret('committed-secret');
        $app->setUnconfirmedAppSecrets(['pending-secret']);
        $appRepository = AppFixture::createAppRepository($app);
        $appRepository->addSearch(new AppCollection([$app]));
        $failure = AppException::registrationFailed('test', 'confirm timed out');

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->willThrowException($failure);
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->configReader->expects($this->never())->method('read');
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->manifestFactory->expects($this->never())->method('createFromApp');

        $this->expectExceptionObject($failure);

        $this->createAppManager($appRepository)->install(
            ManifestFixture::empty()->withSetup(),
            new AppInstallParameters(),
            $context
        );
    }

    public function testRefreshRegistrationRefreshesRemoteRegistrationWithoutLifecycleEvents(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: true);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn($manifest);

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($app->getId(), $context, AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $this->scriptExecutor->expects($this->never())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $appManager = $this->createAppManager(AppFixture::createAppRepository($app));

        $appManager->refreshRegistration($app, $context);
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
    }

    public function testRefreshRegistrationSkipsAppsWithoutSetup(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: true);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn(ManifestFixture::empty());

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $this->scriptExecutor->expects($this->never())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(AppFixture::createAppRepository($app))->refreshRegistration($app, $context);
    }

    public function testRefreshRegistrationRecoversPendingSecretForSameIdentityMove(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: true);
        $app->setUnconfirmedAppSecrets(['pending-secret']);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->willReturn(ManifestFixture::empty()->withSetup());

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($app->getId(), $context, AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(AppFixture::createAppRepository($app))->refreshRegistration($app, $context);
    }

    public function testReregisterReplaysInstallAndActivationForActiveApps(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: true);
        $aclRole = new AclRoleEntity();
        $aclRole->setId($app->getAclRoleId());
        $aclRole->setPrivileges(['customer:read', 'order:read', 'product:read']);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn($manifest);

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($app->getId(), $context, AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $this->scriptExecutor->expects($this->exactly(2))->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $appManager = $this->createAppManager(AppFixture::createAppRepository($app), aclRole: $aclRole);

        $appManager->reregister($app, $context);
        $installedEvents = $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class);
        static::assertCount(1, $installedEvents);
        static::assertSame($app, $installedEvents[0]->getApp());

        $permissionEvents = $this->eventDispatcher->getEventsOfClass(AppPermissionsUpdated::class);
        static::assertCount(1, $permissionEvents);
        static::assertSame($app->getId(), $permissionEvents[0]->appId);
        static::assertSame(['customer:read', 'order:read', 'product:read'], $permissionEvents[0]->permissions);
        static::assertSame($context, $permissionEvents[0]->getContext());

        $activatedEvents = $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class);
        static::assertCount(1, $activatedEvents);
        static::assertSame($app, $activatedEvents[0]->getApp());

        $events = $this->eventDispatcher->getEvents();
        static::assertInstanceOf(AppInstalledEvent::class, $events[0]);
        static::assertInstanceOf(AppPermissionsUpdated::class, $events[1]);
        static::assertInstanceOf(AppActivatedEvent::class, $events[2]);
    }

    public function testReregisterDoesNotReplayActivationForInactiveApps(): void
    {
        $context = Context::createDefaultContext();
        $manifest = ManifestFixture::empty()->withSetup();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn($manifest);

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with($app->getId(), $context, AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $this->scriptExecutor->expects($this->once())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $appManager = $this->createAppManager(AppFixture::createAppRepository($app));

        $appManager->reregister($app, $context);
        $installedEvents = $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class);
        static::assertCount(1, $installedEvents);
        static::assertSame($app, $installedEvents[0]->getApp());

        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppPermissionsUpdated::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
    }

    public function testReregisterSkipsAppsWithoutSetup(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: true);

        $this->manifestFactory->expects($this->once())
            ->method('createFromApp')
            ->with($app)
            ->willReturn(ManifestFixture::empty());

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $this->scriptExecutor->expects($this->never())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(AppFixture::createAppRepository($app))->reregister($app, $context);
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppInstalledEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppPermissionsUpdated::class));
    }

    public function testActivateDoesNothingIfAppIsAlreadyActive(): void
    {
        $app = AppFixture::createAppEntity(id: 'test-app', active: true);
        $appRepository = AppFixture::createAppRepository($app);
        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->never())->method('activate');
        $this->activeAppsLoader->expects($this->never())->method('reset');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->activate($app, Context::createDefaultContext());

        static::assertSame([], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testActivateUpdatesAppAndPersisters(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(id: 'test-app', active: false);
        $appRepository = AppFixture::createAppRepository($app);

        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->once())
            ->method('activate');

        $this->activeAppsLoader->expects($this->once())->method('reset');

        $this->scriptExecutor->expects($this->once())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->activate($app, $context);

        static::assertTrue($app->isActive());
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppActivatedEvent::class));
        static::assertSame([
            ['id' => $app->getId(), 'active' => true],
        ], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateDoesNothingIfAppIsAlreadyInactive(): void
    {
        $app = AppFixture::createAppEntity(id: 'test-app', active: false);
        $appRepository = AppFixture::createAppRepository($app);
        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->never())->method('deactivate');
        $this->activeAppsLoader->expects($this->never())->method('reset');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->deactivate($app, Context::createDefaultContext());

        static::assertSame([], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesAppAndPersisters(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(id: 'test-app', active: true);
        $appRepository = AppFixture::createAppRepository($app);

        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->once())
            ->method('deactivate');

        $this->activeAppsLoader->expects($this->once())->method('reset');

        $this->scriptExecutor->expects($this->once())->method('execute');

        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('removeAssets');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->deactivate($app, $context);

        static::assertFalse($app->isActive());
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppDeactivatedEvent::class));
        static::assertSame([
            ['id' => $app->getId(), 'active' => false],
        ], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateThrowsIfDisableIsNotAllowed(): void
    {
        $app = AppFixture::createAppEntity(id: 'test-app', active: true, allowDisable: false);

        $this->expectNoLifecycleCollaboratorCalls();

        $this->expectException(AppException::class);

        $this->createAppManager(AppFixture::createAppRepository($app))
            ->deactivate($app, Context::createDefaultContext());
    }

    public function testUninstallDeactivatesActiveAppBeforeRemovingData(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(id: 'test-app', active: true, allowDisable: false);
        $appRepository = AppFixture::createAppRepository($app);

        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->once())
            ->method('deactivate');
        $persister->expects($this->once())
            ->method('uninstall');

        $this->customEntityLifecycleService = $this->createMock(CustomEntityLifecycleService::class);
        $this->customEntityLifecycleService->expects($this->once())
            ->method('canRemoveAppData')
            ->with($app)
            ->willReturn(true);
        $this->customEntityLifecycleService->expects($this->once())
            ->method('removeApp')
            ->with($app, $context, true);

        $this->integrationRepository = new StaticEntityRepository([]);
        $this->permissionLifecycle->expects($this->once())->method('softDeleteRole')->with($app->getAclRoleId());

        $this->assetService->expects($this->once())->method('removeAssets')->with($app->getName());

        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->once())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->scriptExecutor->expects($this->exactly(2))->method('execute');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->uninstall($app, $context, true);

        static::assertSame([
            ['id' => $app->getId(), 'active' => false],
        ], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
        static::assertSame([['id' => $app->getId()]], $appRepository->getPayloads(StaticEntityRepository::DELETE));
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(AppDeletedEvent::class));
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(PostAppDeletedEvent::class));

        $integrationUpdates = $this->integrationRepository->getPayloads(StaticEntityRepository::UPDATE);
        static::assertCount(1, $integrationUpdates);
        static::assertSame($app->getIntegrationId(), $integrationUpdates[0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $integrationUpdates[0]['deletedAt']);
    }

    public function testDeleteRemovesAppLocallyWithoutLifecycleEvents(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(id: 'test-app', active: true, allowDisable: false);
        $appRepository = AppFixture::createAppRepository($app);

        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->once())
            ->method('delete');
        $persister->expects($this->never())->method('deactivate');

        $this->integrationRepository = new StaticEntityRepository([]);
        $this->permissionLifecycle->expects($this->once())->method('softDeleteRole')->with($app->getAclRoleId());

        $this->assetService->expects($this->once())->method('removeAssets')->with($app->getName());

        $this->scriptExecutor->expects($this->never())->method('execute');

        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->configReader->expects($this->never())->method('read');

        $this->createAppManager(
            $appRepository,
            persisters: [$persister],
        )->delete($app, $context);

        // the app server is never informed: no deactivation, no app.deleted webhook
        static::assertSame([], $appRepository->getPayloads(StaticEntityRepository::UPDATE));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppDeactivatedEvent::class));
        static::assertCount(0, $this->eventDispatcher->getEventsOfClass(AppDeletedEvent::class));

        static::assertSame([['id' => $app->getId()]], $appRepository->getPayloads(StaticEntityRepository::DELETE));
        static::assertCount(1, $this->eventDispatcher->getEventsOfClass(PostAppDeletedEvent::class));
    }

    public function testDeleteRemovesConfigOnlyWhenUserDataIsNotKept(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $app->setPath('');
        $appRepository = AppFixture::createAppRepository();
        $this->sourceResolver = new StaticSourceResolver([
            'test' => new StaticFilesystem([
                'Resources/config/config.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <card>
        <input-field type="text">
            <name>email</name>
            <label>Email</label>
        </input-field>
    </card>
</config>
XML,
            ]),
        ]);
        $config = [['name' => 'email']];
        $this->configReader->expects($this->once())->method('read')->willReturn($config);

        $this->systemConfigService->expects($this->once())
            ->method('deleteExtensionConfiguration')
            ->with('test', $config);

        $this->permissionLifecycle->expects($this->exactly(2))->method('softDeleteRole');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->assetService->expects($this->exactly(2))->method('removeAssets');
        $this->scriptExecutor->expects($this->never())->method('execute');

        $this->createAppManager($appRepository)->delete($app, $context);

        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');

        $this->createAppManager(AppFixture::createAppRepository())->delete($app, $context, true);
    }

    private function expectNoLifecycleCollaboratorCalls(): void
    {
        $this->permissionLifecycle->expects($this->never())->method('updatePrivileges');
        $this->registrationService->expects($this->never())->method('registerApp');
        $this->appSecretRotationService->expects($this->never())->method('rotateNow');
        $this->manifestFactory->expects($this->never())->method('createFromApp');
        $this->activeAppsLoader->expects($this->never())->method('reset');
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');
        $this->assetService->expects($this->never())->method('copyAssetsFromApp');
        $this->scriptExecutor->expects($this->never())->method('execute');
        $this->configReader->expects($this->never())->method('read');
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     * @param list<AbstractLifecycleHandler> $persisters
     * @param StaticEntityRepository<LanguageCollection>|null $languageRepository
     */
    private function createAppManager(
        StaticEntityRepository $appRepository,
        array $persisters = [],
        ?AclRoleEntity $aclRole = null,
        ?StaticEntityRepository $languageRepository = null,
    ): AppManager {
        $aclRoleRepository = new StaticEntityRepository([new AclRoleCollection($aclRole ? [$aclRole] : [])]);

        return new AppManager(
            $persisters,
            $appRepository,
            $this->permissionLifecycle,
            $this->eventDispatcher,
            $this->registrationService,
            $this->appSecretRotationService,
            $this->manifestFactory,
            $this->activeAppsLoader,
            $languageRepository ?? AppFixture::createLanguageRepository(),
            $this->systemConfigService,
            $this->integrationRepository,
            $aclRoleRepository,
            $this->assetService,
            $this->scriptExecutor,
            __DIR__,
            $this->customEntityLifecycleService,
            static::createStub(AppFeatureValidator::class),
            $this->sourceResolver,
            $this->configReader,
            $this->deletedAppsGateway,
            $this->manifestValidator,
            new NativeClock(),
        );
    }

    private function createDefaultCustomEntityLifecycleService(): CustomEntityLifecycleService
    {
        $customEntityLifecycleService = static::createStub(CustomEntityLifecycleService::class);
        $customEntityLifecycleService->method('allowsDisabling')->willReturn(true);

        return $customEntityLifecycleService;
    }
}
