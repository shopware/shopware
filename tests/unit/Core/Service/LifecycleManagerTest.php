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
use Shopware\Core\Service\ServiceException;
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

    private Context $context;

    protected function setUp(): void
    {
        $this->privileges = $this->createMock(Privileges::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->appLifecycle = $this->createMock(AppLifecycle::class);
        $this->serviceInstaller = $this->createMock(AllServiceInstaller::class);
        $this->permissionsService = $this->createMock(PermissionsService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testInstallWhenEnabled(): void
    {
        $expectedServices = ['service1', 'service2'];

        $this->serviceInstaller->expects($this->once())
            ->method('install')
            ->with($this->context)
            ->willReturn($expectedServices);

        $manager = new LifecycleManager(
            'true',
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository(),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $result = $manager->install($this->context);

        static::assertSame($expectedServices, $result);
    }

    public function testInstallWhenDisabled(): void
    {
        $this->serviceInstaller->expects($this->never())
            ->method('install');

        $manager = new LifecycleManager(
            'false',
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository(),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

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

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository(),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

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
            ->method('delete')
            ->willReturnCallback(function ($name, $options, $context) use ($services): void {
                static::assertContains($name, $services->map(fn (AppEntity $service) => $service->getName()));
                static::assertArrayHasKey('id', $options);
                static::assertSame($this->context, $context);
            });

        $this->permissionsService->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->disable($this->context);
    }

    public function testDisableWithNoServices(): void
    {
        $services = new AppCollection([]);

        $this->appLifecycle->expects($this->never())
            ->method('delete');

        $this->permissionsService->expects($this->once())
            ->method('revoke')
            ->with($this->context);

        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->disable($this->context);
    }

    public function testStart(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(true);

        $this->privileges
            ->expects($this->once())
            ->method('acceptAllForApps')
            ->with($services->getIds(), $this->context);

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->start($this->context);
    }

    public function testStartWithoutPermissionsConsent(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(false);

        $this->privileges
            ->expects($this->never())
            ->method('acceptAllForApps');

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('The services are in an invalid state. Cannot start if the consent is not given.');

        $manager->start($this->context);
    }

    public function testStop(): void
    {
        $services = new AppCollection([
            (new AppEntity())->assign(['id' => 'service1', 'name' => 'SwagService1']),
            (new AppEntity())->assign(['id' => 'service2', 'name' => 'SwagService2']),
            (new AppEntity())->assign(['id' => 'service3', 'name' => 'SwagService3']),
        ]);

        $this->privileges
            ->expects($this->once())
            ->method('revokeAllForApps')
            ->with($services->getIds(), $this->context);

        $manager = new LifecycleManager(
            LifecycleManager::AUTO_ENABLED,
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->stop($this->context);
    }

    public function testSyncStateServiceNotFound(): void
    {
        $serviceName = 'NonExistentService';

        $manager = new LifecycleManager(
            'true',
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository(),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('The service is not installed.');

        $manager->syncState($serviceName, $this->context);
    }

    public function testSyncStateWithAcceptedPermissions(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = (new AppEntity())->assign([
            'id' => $serviceId,
            'name' => $serviceName,
            'selfManaged' => true,
        ]);

        $services = new AppCollection([$service]);

        $this->permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(true);

        $this->privileges->expects($this->once())
            ->method('acceptAllForApps')
            ->with([$serviceId], $this->context);

        $this->privileges->expects($this->never())
            ->method('revokeAllForApps');

        $manager = new LifecycleManager(
            'true',
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->syncState($serviceName, $this->context);
    }

    public function testSyncStateWithRevokedPermissions(): void
    {
        $serviceName = 'TestService';
        $serviceId = 'service-id-123';

        $service = (new AppEntity())->assign([
            'id' => $serviceId,
            'name' => $serviceName,
            'selfManaged' => true,
        ]);

        $services = new AppCollection([$service]);

        $this->permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(false);

        $this->privileges->expects($this->never())
            ->method('acceptAllForApps');

        $this->privileges->expects($this->once())
            ->method('revokeAllForApps')
            ->with([$serviceId], $this->context);

        $manager = new LifecycleManager(
            'true',
            'prod',
            $this->privileges,
            $this->systemConfigService,
            $this->createAppRepository($services),
            $this->appLifecycle,
            $this->serviceInstaller,
            $this->permissionsService
        );

        $manager->syncState($serviceName, $this->context);
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
}
