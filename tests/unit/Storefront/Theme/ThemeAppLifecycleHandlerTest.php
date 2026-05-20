<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
#[CoversClass(ThemeAppLifecycleHandler::class)]
class ThemeAppLifecycleHandlerTest extends TestCase
{
    public function testActivationUsesExistingConfiguration(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $configurations = new StorefrontPluginConfigurationCollection([$config]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->never())->method('createFromApp');

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->with($config, $configurations, static::isInstanceOf(Context::class));
        $lifecycle->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler($registry, $factory, $lifecycle);

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }

    public function testActivationCreatesConfigurationWhenMissing(): void
    {
        $configurations = new StorefrontPluginConfigurationCollection();
        $newConfig = new StorefrontPluginConfiguration('ComponentTestApp');

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $factory->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn($newConfig);

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->once())->method('handleThemeInstallOrUpdate');
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler($registry, $factory, $lifecycle);

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }

    public function testUninstallWithConfigCallsThemeUninstallAndRefresh(): void
    {
        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $configurations = new StorefrontPluginConfigurationCollection([$config]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($configurations);

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->once())->method('handleThemeUninstall')->with($config, static::isInstanceOf(Context::class));
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler($registry, $factory, $lifecycle);

        $app = (new AppEntity())->assign(['name' => 'ComponentTestApp']);
        $handler->handleUninstall(new AppDeactivatedEvent($app, Context::createDefaultContext()));
    }

    public function testUninstallWithoutConfigOnlyRefreshesImportMap(): void
    {
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $factory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->never())->method('handleThemeUninstall');
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler($registry, $factory, $lifecycle);

        $app = (new AppEntity())->assign(['name' => 'ComponentTestApp']);
        $handler->handleUninstall(new AppDeactivatedEvent($app, Context::createDefaultContext()));
    }
}
