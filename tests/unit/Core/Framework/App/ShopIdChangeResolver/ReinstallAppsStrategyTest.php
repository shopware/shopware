<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(ReinstallAppsStrategy::class)]
class ReinstallAppsStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $this->createMock(AppManager::class),
            $this->createMock(ShopIdProvider::class)
        );

        static::assertSame(ReinstallAppsStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotEmpty($strategy->getDescription());
    }

    public function testDeletesShopIdAndReregistersEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider
        );

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $calledApps);
    }

    public function testContinuesWithRemainingAppsAndReportsFailuresTogether(): void
    {
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function () use (&$calls): void {
                if (++$calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider
        );

        $this->expectExceptionObject(AppException::reRegistrationFailed(['app-one']));

        $strategy->resolve(Context::createDefaultContext());
    }
}
