<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServicesAvailability;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

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

    private Context $context;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->serviceLifecycle = static::createStub(ServiceLifecycle::class);
        $this->serviceInstaller = static::createStub(AllServiceInstaller::class);
        $this->permissionsService = static::createStub(PermissionsService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testEnable(): void
    {
        $this->systemConfigService->set(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED, true);
        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->once())->method('scheduleInstall');
        $this->serviceInstaller = $installer;

        $manager = $this->createManager();

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

        $this->createManager()->disable($this->context);

        static::assertTrue($this->systemConfigService->getBool(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED));
    }

    public function testSyncUninstallsRetiredServicesBeforeReevaluating(): void
    {
        $calls = [];

        $installer = $this->createMock(AllServiceInstaller::class);
        $installer->expects($this->once())
            ->method('uninstallRetired')
            ->with($this->context)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'uninstallRetired';
            });
        $this->serviceInstaller = $installer;

        $lifecycle = $this->createMock(ServiceLifecycle::class);
        $lifecycle->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'reevaluateInstalled';
            });
        $this->serviceLifecycle = $lifecycle;

        $this->createManager()->sync($this->context);

        static::assertSame(['uninstallRetired', 'reevaluateInstalled'], $calls);
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

        $manager = $this->createManager();

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

        $manager = $this->createManager(setting: 'false');

        static::assertSame([], $manager->reconcile($this->context));
    }

    /**
     * @param array<string, bool> $systemConfig
     */
    #[DataProvider('enabledProvider')]
    public function testEnabled(string $setting, string $appEnv, array $systemConfig, bool $expectedEnabled): void
    {
        $this->systemConfigService = new StaticSystemConfigService($systemConfig);

        $manager = $this->createManager(setting: $setting, appEnv: $appEnv);

        static::assertSame($expectedEnabled, $manager->enabled());
    }

    public static function enabledProvider(): \Generator
    {
        yield 'services the environment turns on are enabled' => [
            ServicesAvailability::AUTO,
            'prod',
            [],
            true,
        ];

        yield 'services the environment turns off are not enabled' => [
            ServicesAvailability::AUTO,
            'dev',
            [],
            false,
        ];

        yield 'the persisted disabled flag does not change it' => [
            'true',
            'prod',
            [LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true],
            true,
        ];
    }

    public function testAvailabilityIsBuiltFromTheEnvironment(): void
    {
        $manager = $this->createManager(setting: ServicesAvailability::AUTO, appEnv: 'dev');

        static::assertEquals(new ServicesAvailability(ServicesAvailability::AUTO, 'dev'), $manager->availability());
    }

    private function createManager(string $setting = 'true', string $appEnv = 'prod'): LifecycleManager
    {
        return new LifecycleManager(
            new ServicesAvailability($setting, $appEnv),
            $this->systemConfigService,
            $this->serviceLifecycle,
            $this->serviceInstaller,
            $this->permissionsService,
        );
    }
}
