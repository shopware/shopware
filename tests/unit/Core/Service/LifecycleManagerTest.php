<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\Requirement\ServiceRequirement;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LifecycleManager::class)]
class LifecycleManagerTest extends TestCase
{
    private Privileges&MockObject $privileges;

    private SystemConfigService&MockObject $systemConfigService;

    private readonly ServiceLifecycle&MockObject $serviceLifecycle;

    private AllServiceInstaller&MockObject $serviceInstaller;

    private PermissionsService&MockObject $permissionsService;

    private Client&MockObject $client;

    private ServiceRequirement&MockObject $serviceConsentRequirement;

    private Context $context;

    protected function setUp(): void
    {
        $this->privileges = $this->createMock(Privileges::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $this->serviceInstaller = $this->createMock(AllServiceInstaller::class);
        $this->permissionsService = $this->createMock(PermissionsService::class);
        $this->client = $this->createMock(Client::class);
        $this->serviceConsentRequirement = $this->createMock(ServiceRequirement::class);
        $this->serviceConsentRequirement->method('getGate')->willReturn(Gate::PRIVILEGES);
        $this->context = Context::createDefaultContext();
    }

    public function testEnable(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('delete')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED);

        $this->serviceInstaller->expects($this->once())
            ->method('scheduleInstall');

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = $this->createManager($this->createAppRepository());

        $manager->enable();
    }

    public function testDisable(): void
    {
        $this->serviceLifecycle->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        $this->permissionsService->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $this->createManager($this->createAppRepository())->disable($this->context);
    }

    public function testSyncStateServiceNotFound(): void
    {
        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = $this->createManager($this->createAppRepository());

        $this->expectExceptionObject(ServiceException::serviceNotInstalled('NonExistentService'));

        $manager->syncState('NonExistentService', $this->context);
    }

    public function testSyncStateGrantsWhenRequirementsMet(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = $this->createServiceEntity($serviceId, $serviceName, ['service_consent']);

        $services = new AppCollection([$service]);

        $this->serviceConsentRequirement->expects($this->once())
            ->method('isSatisfied')
            ->willReturn(true);

        $this->privileges->expects($this->once())
            ->method('acceptAllForApps')
            ->with([$serviceId], $this->context);

        $this->privileges->expects($this->never())
            ->method('revokeAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncState($serviceName, $this->context);
    }

    public function testSyncStateRevokesWhenRequirementsNotMet(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = $this->createServiceEntity($serviceId, $serviceName, ['service_consent']);

        $services = new AppCollection([$service]);

        $this->serviceConsentRequirement->expects($this->once())
            ->method('isSatisfied')
            ->willReturn(false);

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');

        $this->privileges->expects($this->once())
            ->method('revokeAllForApps')
            ->with([$serviceId], $this->context);

        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncState($serviceName, $this->context);
    }

    public function testReevaluateRequirementProcessesServicesThatDeclareIt(): void
    {
        $app1 = $this->createServiceEntity('id-1', 'Service1', ['service_consent']);
        $app2 = $this->createServiceEntity('id-2', 'Service2', ['service_consent']);
        $services = new AppCollection([$app1, $app2]);

        $this->serviceConsentRequirement->expects($this->exactly(2))
            ->method('isSatisfied')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->privileges->expects($this->once())
            ->method('acceptAllForApps')
            ->with(['id-1'], $this->context);

        $this->privileges->expects($this->once())
            ->method('revokeAllForApps')
            ->with(['id-2'], $this->context);

        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->reevaluateRequirement('service_consent', $this->context);
    }

    public function testReevaluateRequirementIgnoresServicesThatDoNotDeclareIt(): void
    {
        $services = new AppCollection([
            $this->createServiceEntity('id-1', 'Service1', ['service_consent']),
        ]);

        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');

        $this->privileges->expects($this->never())
            ->method('revokeAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->reevaluateRequirement('shopware_account', $this->context);
    }

    public function testSync(): void
    {
        $services = new AppCollection([
            $this->createServiceEntity('service1', 'SwagService1'),
            $this->createServiceEntity('service2', 'SwagService2'),
            $this->createServiceEntity('service3', 'OrphanedService'),
        ]);

        $this->client->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('SwagService1', 'Swag Service 1', 'https:/example.com', '/app-endpoint'),
                new ServiceEntry('SwagService2', 'Swag Service 2', 'https://swag-service2.example.com', '/app-endpoint'),
            ]);

        $this->serviceLifecycle->expects($this->once())
            ->method('uninstall')
            ->with('OrphanedService', $this->context);

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->sync($this->context);
    }

    public function testReconcileDelegatesToInstallerAndDoesNotRemoveOrphansWhenEnabled(): void
    {
        $expectedServices = ['service1', 'service2'];

        $this->serviceInstaller->expects($this->once())
            ->method('reconcile')
            ->with($this->context)
            ->willReturn($expectedServices);

        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = $this->createManager($this->createAppRepository());

        static::assertSame($expectedServices, $manager->reconcile($this->context));
    }

    public function testReconcileDoesNothingWhenServicesDisabled(): void
    {
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = $this->createManager($this->createAppRepository(), enabled: 'false');

        static::assertSame([], $manager->reconcile($this->context));
    }

    /**
     * @param array<string, bool> $systemConfig
     */
    #[DataProvider('enabledProvider')]
    public function testEnabled(string $envEnabled, string $appEnv, array $systemConfig, bool $expectedEnabled): void
    {
        // the manager below is built from fresh stubs; the shared mocks must stay untouched
        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');
        $this->systemConfigService->expects($this->never())
            ->method('set');
        $this->serviceLifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceInstaller->expects($this->never())
            ->method('reconcile');
        $this->permissionsService->expects($this->never())
            ->method('revoke');
        $this->client->expects($this->never())
            ->method('getAll');
        $this->serviceConsentRequirement->expects($this->never())
            ->method('isSatisfied');

        $manager = new LifecycleManager(
            $envEnabled,
            $appEnv,
            static::createStub(Privileges::class),
            new StaticSystemConfigService($systemConfig),
            new ServiceStorage($this->createAppRepository()),
            static::createStub(ServiceLifecycle::class),
            static::createStub(AllServiceInstaller::class),
            static::createStub(PermissionsService::class),
            static::createStub(Client::class),
            static::createStub(RequirementsValidator::class),
        );

        static::assertSame($expectedEnabled, $manager->enabled());
    }

    public static function enabledProvider(): \Generator
    {
        yield 'auto enabled in prod environment, no system config' => [
            LifecycleManager::AUTO_ENABLED,
            'prod',
            [],
            true,
        ];

        yield 'auto enabled in dev environment, no system config' => [
            LifecycleManager::AUTO_ENABLED,
            'dev',
            [],
            false,
        ];

        yield 'explicitly enabled, prod environment, no system config' => [
            'true',
            'prod',
            [],
            true,
        ];

        yield 'explicitly disabled, prod environment, no system config' => [
            'false',
            'prod',
            [],
            false,
        ];

        yield 'auto enabled in prod, system config disabled is ignored by enabled check' => [
            LifecycleManager::AUTO_ENABLED,
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true],
            true,
        ];

        yield 'explicitly enabled, system config disabled is ignored by enabled check' => [
            'true',
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true],
            true,
        ];

        yield 'auto enabled in prod, system config set to false' => [
            LifecycleManager::AUTO_ENABLED,
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => false],
            true,
        ];
    }

    /**
     * @param StaticEntityRepository<AppCollection> $repository
     */
    private function createManager(
        StaticEntityRepository $repository,
        string $enabled = 'true',
    ): LifecycleManager {
        $requirements = new RequirementsValidator(['service_consent' => $this->serviceConsentRequirement]);

        return new LifecycleManager(
            $enabled,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            new ServiceStorage($repository),
            $this->serviceLifecycle,
            $this->serviceInstaller,
            $this->permissionsService,
            $this->client,
            $requirements,
        );
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function createAppRepository(AppCollection $apps = new AppCollection()): StaticEntityRepository
    {
        $appRepository = new StaticEntityRepository([
            $apps,
        ]);

        return $appRepository;
    }

    /**
     * @param list<string> $requirements
     */
    private function createServiceEntity(string $id, string $name, array $requirements = ['service_consent']): AppEntity
    {
        return AppFixture::createAppEntity(name: $name, id: $id)->assign([
            'version' => '1.0.0',
            'aclRoleId' => 'acl-role-id-' . $id,
            'active' => true,
            'selfManaged' => true,
            'sourceConfig' => $this->createSourceConfig($requirements),
        ]);
    }

    /**
     * @param list<string> $requirements
     *
     * @return array<string, mixed>
     */
    private function createSourceConfig(array $requirements = ['service_consent']): array
    {
        $sourceConfig = [
            'version' => '1.0.0',
            'hash' => 'a453f',
            'revision' => '1.0.0-a453f',
            'zip-url' => 'https://example.com/zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => $requirements,
        ];

        return $sourceConfig;
    }
}
