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

        $manager = $this->createManager(enabled: 'false');

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
            static::createStub(ServiceLifecycle::class),
            static::createStub(AllServiceInstaller::class),
            static::createStub(PermissionsService::class),
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

    private function createManager(string $enabled = 'true'): LifecycleManager
    {
        return new LifecycleManager(
            $enabled,
            'prod',
            $this->systemConfigService,
            $this->serviceLifecycle,
            $this->serviceInstaller,
            $this->permissionsService,
        );
    }
}
