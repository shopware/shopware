<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeAppsUninstalledHandler;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
#[CoversClass(ThemeAppsUninstalledHandler::class)]
class ThemeAppsUninstalledHandlerTest extends TestCase
{
    #[TestDox('Cleans up themes for the uninstalled apps and refreshes the remaining import maps')]
    public function testCleansUpThemesForUninstalledApps(): void
    {
        $config = new StorefrontPluginConfiguration('SwagTheme');

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->once())->method('handleThemeUninstall')->with($config, static::isInstanceOf(Context::class));
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $subscriber = new ThemeAppsUninstalledHandler($registry, $lifecycle);
        $subscriber->uninstalled($this->apps('SwagTheme', 'PlainApp'), Context::createDefaultContext());
    }

    #[TestDox('Skips apps without a theme configuration but still refreshes import maps')]
    public function testSkipsAppsWithoutThemeConfig(): void
    {
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->never())->method('handleThemeUninstall');
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $subscriber = new ThemeAppsUninstalledHandler($registry, $lifecycle);
        $subscriber->uninstalled($this->apps('PlainApp'), Context::createDefaultContext());
    }

    private function apps(string ...$names): AppCollection
    {
        $apps = [];
        foreach ($names as $i => $name) {
            $app = new AppEntity();
            $app->setUniqueIdentifier('app-' . $i);
            $app->assign(['id' => 'app-' . $i, 'name' => $name]);
            $apps[] = $app;
        }

        return new AppCollection($apps);
    }
}
