<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AppsUninstalledHandler;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(UninstallAppsStrategy::class)]
class UninstallAppsStrategyTest extends TestCase
{
    #[TestDox('Notifies subscribers with the live apps before deleting them, and regenerates the shop id')]
    public function testNotifiesSubscribersBeforeDeletingApps(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $subscriber = $this->createMock(AppsUninstalledHandler::class);
        $subscriber->expects($this->once())
            ->method('uninstalled')
            ->with(
                static::callback(static fn (AppCollection $apps): bool => $apps->count() === 2),
                static::isInstanceOf(Context::class),
            );

        $strategy = new UninstallAppsStrategy(
            $this->appRepository('SwagTheme', 'PlainApp'),
            $shopIdProvider,
            [$subscriber],
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    #[TestDox('Skips subscriber notification when no apps are installed')]
    public function testSkipsNotificationWhenNoAppsInstalled(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $subscriber = $this->createMock(AppsUninstalledHandler::class);
        $subscriber->expects($this->never())->method('uninstalled');

        $strategy = new UninstallAppsStrategy(
            $this->appRepository(),
            $shopIdProvider,
            [$subscriber],
        );

        $strategy->resolve(Context::createDefaultContext());
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
