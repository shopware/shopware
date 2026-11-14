<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\PluginComposerDependencyService;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ExtensionLifecycleService::class)]
class ExtensionLifecycleServiceTest extends TestCase
{
    private Context $context;

    private PluginLifecycleService&Stub $pluginLifecycleService;

    private PluginManagementService&Stub $pluginManagementService;

    private PluginComposerDependencyService&Stub $composerDependencyService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->pluginLifecycleService = static::createStub(PluginLifecycleService::class);
        $this->pluginManagementService = static::createStub(PluginManagementService::class);
        $this->composerDependencyService = static::createStub(PluginComposerDependencyService::class);
    }

    #[DataProvider('removablePluginProvider')]
    public function testRemoveUninstallsAnInstalledPluginBeforeDeletingIt(bool $managedByComposer, string $path): void
    {
        $plugin = $this->createPlugin(installed: true, managedByComposer: $managedByComposer, path: $path);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginManagementService = $this->createMock(PluginManagementService::class);

        $steps = [];
        $pluginLifecycleService
            ->expects($this->once())
            ->method('uninstallPlugin')
            ->with($plugin, $this->context, true)
            ->willReturnCallback(static function () use (&$steps): UninstallContext {
                $steps[] = 'uninstall';

                return static::createStub(UninstallContext::class);
            });
        $pluginManagementService
            ->expects($this->once())
            ->method('deletePlugin')
            ->with($plugin, $this->context)
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'delete';
            });

        $this->createService($plugin, $pluginLifecycleService, $pluginManagementService)->remove('plugin', 'SwagExample', true, $this->context);

        static::assertSame(['uninstall', 'delete'], $steps);
    }

    public static function removablePluginProvider(): \Generator
    {
        yield 'plugin uploaded to custom/plugins' => [false, 'custom/plugins/SwagExample/'];
        yield 'plugin that Composer installed into custom/plugins' => [true, 'custom/plugins/SwagExample/'];
    }

    public function testRemoveOnlyDeletesAPluginThatIsNotInstalled(): void
    {
        $plugin = $this->createPlugin(installed: false, managedByComposer: false, path: 'custom/plugins/SwagExample/');

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $pluginManagementService = $this->createMock(PluginManagementService::class);
        $pluginManagementService
            ->expects($this->once())
            ->method('deletePlugin')
            ->with($plugin, $this->context);

        $this->createService($plugin, $pluginLifecycleService, $pluginManagementService)->remove('plugin', 'SwagExample', false, $this->context);
    }

    public function testRemoveRefusesAPluginManagedByComposerBeforeUninstallingIt(): void
    {
        $plugin = $this->createPlugin(installed: true, managedByComposer: true, path: 'vendor/swag/example/');

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $pluginManagementService = $this->createMock(PluginManagementService::class);
        $pluginManagementService->expects($this->never())->method('deletePlugin');

        $this->expectExceptionObject(StoreException::cannotDeleteManaged('SwagExample'));

        $this->createService($plugin, $pluginLifecycleService, $pluginManagementService)->remove('plugin', 'SwagExample', false, $this->context);
    }

    public function testRemoveWithTheSeparateComposerStepRemovesThePackageBeforeTheFiles(): void
    {
        $plugin = $this->createPlugin(installed: true, managedByComposer: true, path: 'custom/plugins/SwagExample/');
        $this->context->addState(PluginLifecycleService::STATE_SEPARATE_COMPOSER_STEP);

        $steps = [];
        $this->pluginLifecycleService->method('uninstallPlugin')->willReturnCallback(static function () use (&$steps): UninstallContext {
            $steps[] = 'uninstall';

            return static::createStub(UninstallContext::class);
        });
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService
            ->expects($this->once())
            ->method('removeDependency')
            ->with($plugin, $this->context)
            ->willReturnCallback(static function () use (&$steps): bool {
                $steps[] = 'composer remove';

                return true;
            });
        $this->pluginManagementService->method('deletePlugin')->willReturnCallback(static function () use (&$steps): void {
            $steps[] = 'delete';
        });

        $this->createService($plugin, composerDependencyService: $composerDependencyService)->remove('plugin', 'SwagExample', false, $this->context);

        static::assertSame(['uninstall', 'composer remove', 'delete'], $steps);
    }

    public function testComposerRequireRequiresThePackageOfAPlugin(): void
    {
        $plugin = $this->createPlugin(installed: false, managedByComposer: false, path: 'custom/plugins/SwagExample/');

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->with($plugin, $this->context);

        $this->createService($plugin, composerDependencyService: $composerDependencyService)->composerRequire('plugin', 'SwagExample', $this->context);
    }

    public function testComposerRemoveRemovesThePackageOfAPlugin(): void
    {
        $plugin = $this->createPlugin(installed: false, managedByComposer: true, path: 'custom/plugins/SwagExample/');

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('removeDependency')->with($plugin, $this->context);

        $this->createService($plugin, composerDependencyService: $composerDependencyService)->composerRemove('plugin', 'SwagExample', $this->context);
    }

    public function testComposerStepIgnoresApps(): void
    {
        $plugin = $this->createPlugin(installed: false, managedByComposer: false, path: 'custom/apps/SwagExample/');

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->never())->method('requireDependency');
        $composerDependencyService->expects($this->never())->method('removeDependency');

        $service = $this->createService($plugin, composerDependencyService: $composerDependencyService);
        $service->composerRequire('app', 'SwagExample', $this->context);
        $service->composerRemove('app', 'SwagExample', $this->context);
    }

    private function createService(
        PluginEntity $plugin,
        ?PluginLifecycleService $pluginLifecycleService = null,
        ?PluginManagementService $pluginManagementService = null,
        ?PluginComposerDependencyService $composerDependencyService = null,
    ): ExtensionLifecycleService {
        $pluginService = static::createStub(PluginService::class);
        $pluginService->method('getPluginByName')->willReturn($plugin);

        return new ExtensionLifecycleService(
            static::createStub(AbstractStoreAppLifecycleService::class),
            $pluginService,
            $pluginLifecycleService ?? $this->pluginLifecycleService,
            $pluginManagementService ?? $this->pluginManagementService,
            $composerDependencyService ?? $this->composerDependencyService,
        );
    }

    private function createPlugin(bool $installed, bool $managedByComposer, string $path): PluginEntity
    {
        return (new PluginEntity())->assign([
            'id' => Uuid::randomHex(),
            'name' => 'SwagExample',
            'path' => $path,
            'managedByComposer' => $managedByComposer,
            'active' => $installed,
            'installedAt' => $installed ? new \DateTimeImmutable() : null,
        ]);
    }
}
