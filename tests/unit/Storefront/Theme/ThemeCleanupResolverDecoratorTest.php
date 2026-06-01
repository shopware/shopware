<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AbstractShopIdChangeStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCleanupResolverDecorator;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
#[CoversClass(ThemeCleanupResolverDecorator::class)]
class ThemeCleanupResolverDecoratorTest extends TestCase
{
    #[TestDox('Uninstall strategy: cleans up themes for the apps that were installed, then refreshes import maps')]
    public function testUninstallStrategyCleansThemesForUninstalledApps(): void
    {
        $config = new StorefrontPluginConfiguration('SwagTheme');

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->once())->method('handleThemeUninstall')->with($config, static::isInstanceOf(Context::class));
        $lifecycle->expects($this->once())->method('refreshAllActiveThemeImportMaps');

        $strategy = $this->uninstallStrategy();
        $strategy->expects($this->once())->method('resolve');

        $decorator = new ThemeCleanupResolverDecorator(
            new Resolver([$strategy]),
            $this->appRepository('SwagTheme', 'PlainApp'),
            $registry,
            $lifecycle,
        );

        $decorator->resolve(UninstallAppsStrategy::STRATEGY_NAME, Context::createDefaultContext());
    }

    #[TestDox('Theme cleanup runs after the inner strategy (which deletes the apps), using the pre-captured names')]
    public function testThemeCleanupRunsAfterInnerResolve(): void
    {
        $order = [];

        $strategy = $this->uninstallStrategy();
        $strategy->method('resolve')->willReturnCallback(static function () use (&$order): void {
            $order[] = 'resolve';
        });

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->method('refreshAllActiveThemeImportMaps')->willReturnCallback(static function () use (&$order): void {
            $order[] = 'cleanup';
        });

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn(new StorefrontPluginConfigurationCollection());

        $decorator = new ThemeCleanupResolverDecorator(
            new Resolver([$strategy]),
            $this->appRepository('SwagTheme'),
            $registry,
            $lifecycle,
        );

        $decorator->resolve(UninstallAppsStrategy::STRATEGY_NAME, Context::createDefaultContext());

        static::assertSame(['resolve', 'cleanup'], $order);
    }

    #[TestDox('Other strategies are delegated untouched: no app lookup and no theme cleanup')]
    public function testOtherStrategiesAreDelegatedWithoutThemeCleanup(): void
    {
        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->expects($this->never())->method('getConfigurations');

        $lifecycle = $this->createMock(ThemeLifecycleHandler::class);
        $lifecycle->expects($this->never())->method('handleThemeUninstall');
        $lifecycle->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $strategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $strategy->method('getName')->willReturn('reinstall-apps');
        $strategy->expects($this->once())->method('resolve');

        $decorator = new ThemeCleanupResolverDecorator(
            new Resolver([$strategy]),
            $this->appRepository(),
            $registry,
            $lifecycle,
        );

        $decorator->resolve('reinstall-apps', Context::createDefaultContext());
    }

    public function testGetAvailableStrategiesIsDelegatedToInner(): void
    {
        $strategy = $this->uninstallStrategy();
        $strategy->method('getDescription')->willReturn('uninstall description');

        $decorator = new ThemeCleanupResolverDecorator(
            new Resolver([$strategy]),
            $this->appRepository(),
            $this->createMock(StorefrontPluginRegistry::class),
            $this->createMock(ThemeLifecycleHandler::class),
        );

        static::assertSame(
            [UninstallAppsStrategy::STRATEGY_NAME => 'uninstall description'],
            $decorator->getAvailableStrategies(),
        );
    }

    private function uninstallStrategy(): AbstractShopIdChangeStrategy&MockObject
    {
        $strategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $strategy->method('getName')->willReturn(UninstallAppsStrategy::STRATEGY_NAME);

        return $strategy;
    }

    /**
     * @return StaticEntityRepository<AppCollection>
     */
    private function appRepository(string ...$names): StaticEntityRepository
    {
        $apps = [];
        foreach ($names as $i => $name) {
            $app = new AppEntity();
            $app->setUniqueIdentifier('app-' . $i);
            $app->assign(['id' => 'app-' . $i, 'name' => $name]);
            $apps[] = $app;
        }

        /** @var StaticEntityRepository<AppCollection> $repository */
        $repository = new StaticEntityRepository([new AppCollection($apps)]);

        return $repository;
    }
}
