<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(LifecycleManager::class)]
class LifecycleManagerTest extends TestCase
{
    private Privileges&MockObject $privileges;

    private SystemConfigService&MockObject $systemConfigService;

    private readonly AppLifecycle&MockObject $appLifecycle;

    private AllServiceInstaller&MockObject $serviceInstaller;

    private PermissionsService&MockObject $permissionsService;

    private Client&MockObject $client;

    private RequirementsValidator&MockObject $requirementsValidator;

    private Context $context;

    protected function setUp(): void
    {
        $this->privileges = $this->createMock(Privileges::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->appLifecycle = $this->createMock(AppLifecycle::class);
        $this->serviceInstaller = $this->createMock(AllServiceInstaller::class);
        $this->permissionsService = $this->createMock(PermissionsService::class);
        $this->client = $this->createMock(Client::class);
        $this->requirementsValidator = $this->createMock(RequirementsValidator::class);
        $this->context = Context::createDefaultContext();
    }

    public function testInstallWhenEnabled(): void
    {
        $expectedServices = ['service1', 'service2'];

        $this->serviceInstaller->expects($this->once())
            ->method('install')
            ->with($this->context)
            ->willReturn($expectedServices);

        $manager = $this->createManager($this->createAppRepository());

        $result = $manager->install($this->context);

        static::assertSame($expectedServices, $result);
    }

    public function testInstallWhenDisabled(): void
    {
        $this->serviceInstaller->expects($this->never())
            ->method('install');

        $manager = $this->createManager($this->createAppRepository(), enabled: 'false');

        $result = $manager->install($this->context);

        static::assertSame([], $result);
    }

    public function testEnable(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('delete')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED);

        $this->serviceInstaller->expects($this->once())
            ->method('scheduleInstall');

        $manager = $this->createManager($this->createAppRepository());

        $manager->enable();
    }

    public function testDisable(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->appLifecycle->expects($this->exactly($services->count()))
            ->method('uninstall')
            ->willReturnCallback(function ($name, $options, $context) use ($services): void {
                static::assertContains($name, $services->map(static fn (AppEntity $service) => $service->getName()));
                static::assertArrayHasKey('id', $options);
                static::assertSame($this->context, $context);
            });

        $this->permissionsService->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->disable($this->context);
    }

    public function testDisableWithNoServices(): void
    {
        $services = new AppCollection([]);

        $this->appLifecycle->expects($this->never())
            ->method('uninstall');

        $this->permissionsService->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->disable($this->context);
    }

    public function testSyncStateServiceNotFound(): void
    {
        $manager = $this->createManager($this->createAppRepository());

        $this->expectExceptionObject(ServiceException::serviceNotInstalled('NonExistentService'));

        $manager->syncState('NonExistentService', $this->context);
    }

    public function testSyncStateGrantsWhenRequirementsMet(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = (new AppEntity())->assign([
            'id' => $serviceId,
            'name' => $serviceName,
            'selfManaged' => true,
        ]);

        $services = new AppCollection([$service]);

        $this->requirementsValidator->expects($this->once())
            ->method('isSatisfied')
            ->with($service)
            ->willReturn(true);

        $this->privileges->expects($this->once())
            ->method('acceptAllForApps')
            ->with([$serviceId], $this->context);

        $this->privileges->expects($this->never())
            ->method('revokeAllForApps');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncState($serviceName, $this->context);
    }

    public function testSyncStateRevokesWhenRequirementsNotMet(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = (new AppEntity())->assign([
            'id' => $serviceId,
            'name' => $serviceName,
            'selfManaged' => true,
        ]);

        $services = new AppCollection([$service]);

        $this->requirementsValidator->expects($this->once())
            ->method('isSatisfied')
            ->with($service)
            ->willReturn(false);

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');

        $this->privileges->expects($this->once())
            ->method('revokeAllForApps')
            ->with([$serviceId], $this->context);

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncState($serviceName, $this->context);
    }

    public function testSyncRequirementReEvaluatesAffectedServices(): void
    {
        $app1 = (new AppEntity())->assign(['id' => 'id-1', 'name' => 'Service1', 'selfManaged' => true, 'sourceConfig' => $this->createSourceConfig(['service_consent'])]);
        $app2 = (new AppEntity())->assign(['id' => 'id-2', 'name' => 'Service2', 'selfManaged' => true, 'sourceConfig' => $this->createSourceConfig(['service_consent'])]);
        $services = new AppCollection([$app1, $app2]);

        $this->requirementsValidator->expects($this->exactly(2))
            ->method('isSatisfied')
            ->willReturnMap([
                [$app1, true],
                [$app2, false],
            ]);

        $this->privileges->expects($this->once())
            ->method('acceptAllForApps')
            ->with(['id-1'], $this->context);

        $this->privileges->expects($this->once())
            ->method('revokeAllForApps')
            ->with(['id-2'], $this->context);

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncRequirement('service_consent', $this->context);
    }

    public function testSyncRequirementDoesNothingWhenNoServicesAffected(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'id-1', 'name' => 'Service1', 'selfManaged' => true, 'sourceConfig' => $this->createSourceConfig(['service_consent'])]),
        ]);

        $this->requirementsValidator->expects($this->never())
            ->method('isSatisfied');

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');

        $this->privileges->expects($this->never())
            ->method('revokeAllForApps');

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->syncRequirement('shopware_account', $this->context);
    }

    public function testSync(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1', 'selfManaged' => true]),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2', 'selfManaged' => true]),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'OrphanedService', 'selfManaged' => true]),
        ]);

        $this->client = $this->createMock(Client::class);
        $this->client->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('SwagService1', 'Swag Service 1', 'https:/example.com', '/app-endpoint'),
                new ServiceEntry('SwagService2', 'Swag Service 2', 'https://swag-service2.example.com', '/app-endpoint'),
            ]);

        $this->appLifecycle->expects($this->once())
            ->method('uninstall')
            ->with('OrphanedService', ['id' => 'service3'], $this->context);

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->sync($this->context);
    }

    /**
     * @param array<string, bool> $systemConfig
     */
    #[DataProvider('enabledProvider')]
    public function testEnabled(string $envEnabled, string $appEnv, array $systemConfig, bool $expectedEnabled): void
    {
        $manager = new LifecycleManager(
            $envEnabled,
            $appEnv,
            $this->createMock(Privileges::class),
            new StaticSystemConfigService($systemConfig),
            $this->createAppRepository(),
            $this->createMock(AppLifecycle::class),
            $this->createMock(AllServiceInstaller::class),
            $this->createMock(PermissionsService::class),
            $this->createMock(Client::class),
            $this->createMock(RequirementsValidator::class),
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

        yield 'auto enabled in prod, but disabled via system config' => [
            LifecycleManager::AUTO_ENABLED,
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true],
            false,
        ];

        yield 'explicitly enabled, but disabled via system config' => [
            'true',
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true],
            false,
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
        return new LifecycleManager(
            $enabled,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $repository,
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService,
            $this->client,
            $this->requirementsValidator,
        );
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function createAppRepository(AppCollection $apps = new AppCollection()): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            $apps,
        ]);

        return $appRepository;
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
