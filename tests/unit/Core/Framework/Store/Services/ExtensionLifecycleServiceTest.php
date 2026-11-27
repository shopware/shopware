<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
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

    private PluginLifecycleService&MockObject $pluginLifecycleService;

    private PluginManagementService&MockObject $pluginManagementService;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $this->pluginManagementService = $this->createMock(PluginManagementService::class);
    }

    #[DataProvider('removablePluginProvider')]
    public function testRemoveUninstallsAnInstalledPluginBeforeDeletingIt(bool $managedByComposer, string $path): void
    {
        $plugin = $this->createPlugin(installed: true, managedByComposer: $managedByComposer, path: $path);

        $steps = [];
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('uninstallPlugin')
            ->with($plugin, $this->context, true)
            ->willReturnCallback(static function () use (&$steps): UninstallContext {
                $steps[] = 'uninstall';

                return static::createStub(UninstallContext::class);
            });
        $this->pluginManagementService
            ->expects($this->once())
            ->method('deletePlugin')
            ->with($plugin, $this->context)
            ->willReturnCallback(static function () use (&$steps): void {
                $steps[] = 'delete';
            });

        $this->createService($plugin)->remove('plugin', 'SwagExample', true, $this->context);

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

        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $this->pluginManagementService
            ->expects($this->once())
            ->method('deletePlugin')
            ->with($plugin, $this->context);

        $this->createService($plugin)->remove('plugin', 'SwagExample', false, $this->context);
    }

    public function testRemoveRefusesAPluginManagedByComposerBeforeUninstallingIt(): void
    {
        $plugin = $this->createPlugin(installed: true, managedByComposer: true, path: 'vendor/swag/example/');

        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $this->pluginManagementService->expects($this->never())->method('deletePlugin');

        $this->expectExceptionObject(StoreException::cannotDeleteManaged('SwagExample'));

        $this->createService($plugin)->remove('plugin', 'SwagExample', false, $this->context);
    }

    private function createService(PluginEntity $plugin): ExtensionLifecycleService
    {
        $pluginService = static::createStub(PluginService::class);
        $pluginService->method('getPluginByName')->willReturn($plugin);

        return new ExtensionLifecycleService(
            static::createStub(AbstractStoreAppLifecycleService::class),
            $pluginService,
            $this->pluginLifecycleService,
            $this->pluginManagementService,
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
