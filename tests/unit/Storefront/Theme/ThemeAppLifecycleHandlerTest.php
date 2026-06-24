<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;

/**
 * @internal
 */
#[CoversClass(ThemeAppLifecycleHandler::class)]
class ThemeAppLifecycleHandlerTest extends TestCase
{
    private StorefrontPluginRegistry&MockObject $registry;

    private AbstractStorefrontPluginConfigurationFactory&MockObject $factory;

    private ThemeLifecycleHandler&MockObject $themeLifecycle;

    private ThemeLifecycleService&MockObject $themeService;

    private ThemeAppLifecycleHandler $handler;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(StorefrontPluginRegistry::class);
        $this->factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $this->themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $this->themeService = $this->createMock(ThemeLifecycleService::class);

        $this->handler = new ThemeAppLifecycleHandler($this->registry, $this->factory, $this->themeLifecycle, $this->themeService);
    }

    public function testActivateUsesExistingConfiguration(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $this->registry->method('getConfigurations')->willReturn($configurations);

        $this->factory->expects($this->never())->method('createFromApp');
        $this->themeLifecycle->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->with($config, $configurations, static::isInstanceOf(Context::class));
        $this->themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $this->handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateCreatesConfigurationWhenMissing(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $this->factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn(new StorefrontPluginConfiguration('ComponentTestApp'));
        $this->themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $this->themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $this->handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateSkipsInactiveApp(): void
    {
        $this->themeLifecycle->expects($this->never())->method('handleThemeInstallOrUpdate');
        $this->themeLifecycle->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $app = (new AppEntity())->assign(['name' => 'ComponentTestApp', 'path' => 'p', 'active' => false]);
        $this->handler->activate(new AppActivationContext($app, Context::createDefaultContext()));
    }

    public function testUpdateSetsUpTheme(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $this->themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $this->themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $context = new AppPersistContext(
            $this->createMock(Manifest::class),
            $this->activeApp(),
            Context::createDefaultContext(),
            new StaticFilesystem(),
            'en-GB',
        );
        $this->handler->update($context);
    }

    public function testDeactivateTearsDownConfigBuiltFromApp(): void
    {
        $builtConfig = new StorefrontPluginConfiguration('ComponentTestApp');
        $this->factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn($builtConfig);

        // the registry still lists the app's own config plus another; the app's own one must be filtered out
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('ComponentTestApp'),
            new StorefrontPluginConfiguration('OtherApp'),
        ]));

        $this->themeLifecycle->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($builtConfig, static::isInstanceOf(Context::class));
        $this->themeLifecycle->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $c): bool => $c->getByTechnicalName('ComponentTestApp') === null && $c->getByTechnicalName('OtherApp') !== null),
            );

        $this->handler->deactivate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallRemovesThemeRecord(): void
    {
        $this->themeLifecycle->expects($this->never())->method('handleThemeUninstall');
        $this->themeService->expects($this->once())
            ->method('removeTheme')
            ->with('ComponentTestApp', static::isInstanceOf(Context::class));

        $this->handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallKeepsRecordWhenUserDataKept(): void
    {
        $this->themeService->expects($this->never())->method('removeTheme');

        $this->handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext(), keepUserData: true));
    }

    public function testDeleteTearsDownConfigButKeepsRecord(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());
        $this->factory->expects($this->once())
            ->method('createFromApp')
            ->willReturn(new StorefrontPluginConfiguration('ComponentTestApp'));

        $this->themeLifecycle->expects($this->once())->method('handleThemeUninstall');
        $this->themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');
        $this->themeService->expects($this->never())->method('removeTheme');

        $this->handler->delete(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    private function activeApp(): AppEntity
    {
        return (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);
    }
}
