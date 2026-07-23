<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
#[Package('discovery')]
#[CoversClass(ThemeAppLifecycleHandler::class)]
class ThemeAppLifecycleHandlerTest extends TestCase
{
    private StorefrontPluginRegistry&Stub $registry;

    private AbstractStorefrontPluginConfigurationFactory&Stub $factory;

    private ThemeLifecycleHandler&Stub $themeLifecycle;

    private ThemeLifecycleService&Stub $themeService;

    protected function setUp(): void
    {
        $this->registry = static::createStub(StorefrontPluginRegistry::class);
        $this->factory = static::createStub(AbstractStorefrontPluginConfigurationFactory::class);
        $this->themeLifecycle = static::createStub(ThemeLifecycleHandler::class);
        $this->themeService = static::createStub(ThemeLifecycleService::class);
    }

    public function testActivateUsesExistingConfiguration(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $configurations = new StorefrontPluginConfigurationCollection([$config]);
        $this->registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->never())->method('createFromApp');

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->with($config, $configurations, static::isInstanceOf(Context::class));
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateCreatesConfigurationWhenMissing(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn(new StorefrontPluginConfiguration('ComponentTestApp'));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->activate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testActivateSkipsInactiveApp(): void
    {
        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->never())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $handler = $this->getHandler(themeLifecycle: $themeLifecycle);
        $app = (new AppEntity())->assign(['name' => 'ComponentTestApp', 'path' => 'p', 'active' => false]);
        $handler->activate(new AppActivationContext($app, Context::createDefaultContext()));
    }

    public function testUpdateSetsUpTheme(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $context = new AppPersistContext(
            static::createStub(Manifest::class),
            $this->activeApp(),
            Context::createDefaultContext(),
            new StaticFilesystem(),
            'en-GB',
        );
        $this->getHandler(themeLifecycle: $themeLifecycle)->update($context);
    }

    public function testDeactivateTearsDownConfigBuiltFromApp(): void
    {
        $builtConfig = new StorefrontPluginConfiguration('ComponentTestApp');
        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn($builtConfig);

        // the registry still lists the app's own config plus another; the app's own one must be filtered out
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('ComponentTestApp'),
            new StorefrontPluginConfiguration('OtherApp'),
        ]));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($builtConfig, static::isInstanceOf(Context::class));
        $themeLifecycle->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with(
                static::isInstanceOf(Context::class),
                static::callback(static fn (StorefrontPluginConfigurationCollection $c): bool => $c->getByTechnicalName('ComponentTestApp') === null && $c->getByTechnicalName('OtherApp') !== null),
            );

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle);
        $handler->deactivate(new AppActivationContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallRemovesThemeRecord(): void
    {
        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->never())->method('handleThemeUninstall');

        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->once())
            ->method('removeTheme')
            ->with('ComponentTestApp', static::isInstanceOf(Context::class));

        $handler = $this->getHandler(themeLifecycle: $themeLifecycle, themeService: $themeService);
        $handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    public function testUninstallKeepsRecordWhenUserDataKept(): void
    {
        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->never())->method('removeTheme');

        $handler = $this->getHandler(themeService: $themeService);
        $handler->uninstall(new AppRemovalContext($this->activeApp(), Context::createDefaultContext(), keepUserData: true));
    }

    public function testDeleteTearsDownConfigButKeepsRecord(): void
    {
        $this->registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->willReturn(new StorefrontPluginConfiguration('ComponentTestApp'));

        $themeLifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycle->expects($this->once())->method('handleThemeUninstall');
        $themeLifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $themeService = $this->createMock(ThemeLifecycleService::class);
        $themeService->expects($this->never())->method('removeTheme');

        $handler = $this->getHandler(factory: $factory, themeLifecycle: $themeLifecycle, themeService: $themeService);
        $handler->delete(new AppRemovalContext($this->activeApp(), Context::createDefaultContext()));
    }

    private function getHandler(
        (AbstractStorefrontPluginConfigurationFactory&MockObject)|null $factory = null,
        (ThemeLifecycleHandler&MockObject)|null $themeLifecycle = null,
        (ThemeLifecycleService&MockObject)|null $themeService = null,
    ): ThemeAppLifecycleHandler {
        return new ThemeAppLifecycleHandler(
            $this->registry,
            $factory ?? $this->factory,
            $themeLifecycle ?? $this->themeLifecycle,
            $themeService ?? $this->themeService,
        );
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
