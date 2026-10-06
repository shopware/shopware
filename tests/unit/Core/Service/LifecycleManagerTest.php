<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceStorage;
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
    private StaticSystemConfigService $systemConfigService;

    private ServiceLifecycle $serviceLifecycle;

    private AllServiceInstaller $serviceInstaller;

    private PermissionsService $permissionsService;

    private Client $client;

    private Context $context;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->serviceLifecycle = static::createStub(ServiceLifecycle::class);
        $this->serviceInstaller = static::createStub(AllServiceInstaller::class);
        $this->permissionsService = static::createStub(PermissionsService::class);
        $this->client = static::createStub(Client::class);
        $this->context = Context::createDefaultContext();
    }

    public function testEnable(): void
    {
        $this->systemConfigService->set(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->once())->method('scheduleInstall');
        $this->serviceInstaller = $installer;

        $manager = $this->createManager($this->createAppRepository());

        $manager->enable();

        static::assertNull($this->systemConfigService->get(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED));
    }

    public function testDisable(): void
    {
        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);
        $this->serviceLifecycle = $lifecycle;

        $permissions = $this->createMock(PermissionsService::class);
        $permissions->expects($this->once())
            ->method('revoke')
            ->with($this->context);
        $this->permissionsService = $permissions;

        $this->createManager($this->createAppRepository())->disable($this->context);

        static::assertTrue($this->systemConfigService->getBool(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED));
    }

    public function testSync(): void
    {
        $services = new AppCollection([
            $this->createServiceEntity(id: 'service1', name: 'SwagService1'),
            $this->createServiceEntity(id: 'service2', name: 'SwagService2'),
            $this->createServiceEntity(id: 'service3', name: 'OrphanedService'),
        ]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getAll')
            ->willReturn([
                new ServiceEntry('SwagService1', 'Swag Service 1', 'https://example.com', '/app-endpoint'),
                new ServiceEntry('SwagService2', 'Swag Service 2', 'https://swag-service2.example.com', '/app-endpoint'),
            ]);
        $this->client = $client;

        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects($this->once())
            ->method('uninstall')
            ->with('OrphanedService', $this->context);
        $lifecycle->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);
        $this->serviceLifecycle = $lifecycle;

        $manager = $this->createManager($this->createAppRepository($services));

        $manager->sync($this->context);
    }

    public function testReconcileDelegatesToInstallerAndDoesNotRemoveOrphansWhenEnabled(): void
    {
        $expectedServices = ['service1', 'service2'];

        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->once())
            ->method('reconcile')
            ->with($this->context)
            ->willReturn($expectedServices);
        $this->serviceInstaller = $installer;

        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);
        $lifecycle->expects($this->never())
            ->method('uninstall');
        $this->serviceLifecycle = $lifecycle;

        $manager = $this->createManager($this->createAppRepository());

        static::assertSame($expectedServices, $manager->reconcile($this->context));
    }

    public function testReconcileDoesNothingWhenServicesDisabled(): void
    {
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->never())
            ->method('reconcile');
        $this->serviceInstaller = $installer;
        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects($this->never())
            ->method('reevaluateInstalled');
        $this->serviceLifecycle = $lifecycle;

        $manager = $this->createManager($this->createAppRepository(), enabled: 'false');

        static::assertSame([], $manager->reconcile($this->context));
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
            new StaticSystemConfigService($systemConfig),
            new ServiceStorage($this->createAppRepository()),
            static::createStub(ServiceLifecycle::class),
            static::createStub(AllServiceInstaller::class),
            static::createStub(PermissionsService::class),
            static::createStub(Client::class),
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
        return new LifecycleManager(
            $enabled,
            'prod',
            $this->systemConfigService,
            new ServiceStorage($repository),
            $this->serviceLifecycle,
            $this->serviceInstaller,
            $this->permissionsService,
            $this->client,
        );
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function createAppRepository(AppCollection $apps = new AppCollection()): StaticEntityRepository
    {
        return new StaticEntityRepository([$apps]);
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
        return [
            'version' => '1.0.0',
            'hash' => 'a453f',
            'revision' => '1.0.0-a453f',
            'zip-url' => 'https://example.com/zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => $requirements,
        ];
    }
}
