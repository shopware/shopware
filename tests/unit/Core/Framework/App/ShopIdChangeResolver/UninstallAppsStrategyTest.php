<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(UninstallAppsStrategy::class)]
class UninstallAppsStrategyTest extends TestCase
{
    public function testDeletesShopIdAndDeletesEveryAppLocally(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $deletedApps = [];
        $appManager->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$deletedApps, $context): void {
                $deletedApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new UninstallAppsStrategy($appRepository, $shopIdProvider, $appManager);

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $deletedApps);
    }
}
