<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Handler\AbstractLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\Integration\IntegrationCollection;
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
#[CoversClass(AppManager::class)]
class AppManagerTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private PermissionLifecycleService $permissionLifecycle;

    private AppRegistrationService $registrationService;

    private ActiveAppsLoader $activeAppsLoader;

    private SystemConfigService $systemConfigService;

    /**
     * @var StaticEntityRepository<IntegrationCollection>
     */
    private StaticEntityRepository $integrationRepository;

    private AssetService $assetService;

    private ScriptExecutor $scriptExecutor;

    private CustomEntityLifecycleService $customEntityLifecycleService;

    private SourceResolver $sourceResolver;

    private ConfigReader $configReader;

    private AppRequirementsValidator $requirementsValidator;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->permissionLifecycle = $this->createMock(PermissionLifecycleService::class);
        $this->registrationService = $this->createMock(AppRegistrationService::class);
        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->integrationRepository = new StaticEntityRepository([]);
        $this->assetService = $this->createMock(AssetService::class);
        $this->scriptExecutor = $this->createMock(ScriptExecutor::class);
        $this->customEntityLifecycleService = $this->createDefaultCustomEntityLifecycleService();
        $this->sourceResolver = new StaticSourceResolver();
        $this->configReader = $this->createMock(ConfigReader::class);
        $this->requirementsValidator = static::createStub(AppRequirementsValidator::class);
    }

    public function testInstallThrowsIfAppIsNotCompatible(): void
    {
        $manifest = ManifestFixture::empty();
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);

        $this->expectExceptionObject(AppException::notCompatible('test'));

        $this->createAppManager(AppFixture::createAppRepository())
            ->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testUpdateThrowsIfAppIsNotCompatible(): void
    {
        $manifest = ManifestFixture::empty();
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);

        $this->expectExceptionObject(AppException::notCompatible('test'));

        $this->createAppManager(AppFixture::createAppRepository())
            ->update($manifest, new AppUpdateParameters(), AppFixture::createAppEntity(active: false), Context::createDefaultContext());
    }

    public function testInstallThrowsWhenRequirementsAreNotMet(): void
    {
        $manifest = ManifestFixture::empty();
        $violation = new UnmetRequirement('test', 'https', 'Use HTTPS');

        $requirementsValidator = $this->createMock(AppRequirementsValidator::class);
        $requirementsValidator->expects($this->once())
            ->method('validate')
            ->with($manifest)
            ->willReturn([$violation]);

        $this->expectExceptionObject(AppException::requirementsNotMet($violation));

        $this->requirementsValidator = $requirementsValidator;

        $this->createAppManager(AppFixture::createAppRepository())
            ->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testUpdateThrowsWhenRequirementsAreNotMet(): void
    {
        $manifest = ManifestFixture::empty();
        $violation = new UnmetRequirement('test', 'https', 'Use HTTPS');

        $requirementsValidator = $this->createMock(AppRequirementsValidator::class);
        $requirementsValidator->expects($this->once())
            ->method('validate')
            ->with($manifest)
            ->willReturn([$violation]);

        $this->expectExceptionObject(AppException::requirementsNotMet($violation));

        $this->requirementsValidator = $requirementsValidator;

        $this->createAppManager(AppFixture::createAppRepository())
            ->update($manifest, new AppUpdateParameters(), AppFixture::createAppEntity(active: false), Context::createDefaultContext());
    }

    public function testInstallThrowsIfAppAlreadyExists(): void
    {
        $existingApp = AppFixture::createAppEntity(name: 'test', id: 'test-app', active: false);
        $appRepository = AppFixture::createAppRepository($existingApp);

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

        $this->registrationService = $this->createMock(AppRegistrationService::class);
        $this->registrationService->expects($this->once())
            ->method('registerApp')
            ->willThrowException(AppException::registrationFailed('test', 'registration failed'));

        $this->integrationRepository = new StaticEntityRepository([]);
        $this->permissionLifecycle = $this->createMock(PermissionLifecycleService::class);
        $this->permissionLifecycle->expects($this->once())->method('removeRole');

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

    public function testActivateDoesNothingIfAppIsAlreadyActive(): void
    {
        $app = AppFixture::createAppEntity(id: 'test-app', active: true);
        $appRepository = AppFixture::createAppRepository($app);
        $persister = $this->createMock(AbstractLifecycleHandler::class);
        $persister->expects($this->never())->method('activate');
        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->activeAppsLoader->expects($this->never())->method('reset');

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

        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->activeAppsLoader->expects($this->once())->method('reset');

        $this->scriptExecutor = $this->createMock(ScriptExecutor::class);
        $this->scriptExecutor->expects($this->once())->method('execute');

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
        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->activeAppsLoader->expects($this->never())->method('reset');

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

        $this->activeAppsLoader = $this->createMock(ActiveAppsLoader::class);
        $this->activeAppsLoader->expects($this->once())->method('reset');

        $this->scriptExecutor = $this->createMock(ScriptExecutor::class);
        $this->scriptExecutor->expects($this->once())->method('execute');

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
        $this->permissionLifecycle = $this->createMock(PermissionLifecycleService::class);
        $this->permissionLifecycle->expects($this->once())->method('softDeleteRole')->with($app->getAclRoleId());

        $this->assetService = $this->createMock(AssetService::class);
        $this->assetService->expects($this->once())->method('removeAssets')->with($app->getName());

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
        $this->permissionLifecycle = $this->createMock(PermissionLifecycleService::class);
        $this->permissionLifecycle->expects($this->once())->method('softDeleteRole')->with($app->getAclRoleId());

        $this->assetService = $this->createMock(AssetService::class);
        $this->assetService->expects($this->once())->method('removeAssets')->with($app->getName());

        $this->scriptExecutor = $this->createMock(ScriptExecutor::class);
        $this->scriptExecutor->expects($this->never())->method('execute');

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
        $this->configReader = $this->createMock(ConfigReader::class);
        $this->configReader->expects($this->once())->method('read')->willReturn($config);

        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->systemConfigService->expects($this->once())
            ->method('deleteExtensionConfiguration')
            ->with('test', $config);

        $this->createAppManager($appRepository)->delete($app, $context);

        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->systemConfigService->expects($this->never())->method('deleteExtensionConfiguration');

        $this->createAppManager(AppFixture::createAppRepository())->delete($app, $context, true);
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     * @param list<AbstractLifecycleHandler> $persisters
     */
    private function createAppManager(
        StaticEntityRepository $appRepository,
        array $persisters = [],
    ): AppManager {
        /** @var StaticEntityRepository<AclRoleCollection> $aclRoleRepository */
        $aclRoleRepository = new StaticEntityRepository([new AclRoleCollection()]);

        return new AppManager(
            $persisters,
            $appRepository,
            $this->permissionLifecycle,
            $this->eventDispatcher,
            $this->registrationService,
            $this->activeAppsLoader,
            AppFixture::createLanguageRepository(),
            $this->systemConfigService,
            $this->createMock(ConfigValidator::class),
            $this->integrationRepository,
            $aclRoleRepository,
            $this->assetService,
            $this->scriptExecutor,
            __DIR__,
            $this->customEntityLifecycleService,
            '6.5.0.0',
            $this->createMock(AppFeatureValidator::class),
            $this->sourceResolver,
            $this->configReader,
            $this->createMock(DeletedAppsGateway::class),
            $this->requirementsValidator,
            new NativeClock()
        );
    }

    private function createDefaultCustomEntityLifecycleService(): CustomEntityLifecycleService
    {
        $customEntityLifecycleService = $this->createMock(CustomEntityLifecycleService::class);
        $customEntityLifecycleService->method('allowsDisabling')->willReturn(true);

        return $customEntityLifecycleService;
    }
}
