<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(MoveShopPermanentlyStrategy::class)]
class MoveShopPermanentlyStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $this->createMock(AppManager::class),
            $this->createMock(ShopIdProvider::class)
        );

        static::assertSame(MoveShopPermanentlyStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotEmpty($strategy->getDescription());
    }

    public function testNoResolutionNeededWhenShopIdIsNotSuggestedToChange(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('reset');
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));
        $shopIdProvider->expects($this->never())->method('regenerateAndSetShopId');

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('refreshRegistration');

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testRefreshesRegistrationForEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));
        $shopIdProvider->expects($this->once())
            ->method('regenerateAndSetShopId')
            ->with('shop-id');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveShopPermanentlyStrategy(
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
        $shopIdProvider->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function () use (&$calls): void {
                if (++$calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider
        );

        $this->expectExceptionObject(AppException::reRegistrationFailed(
            ['app-one'],
            recoveryHint: 'After resolving the issue, retry each failed app with "bin/console app:secret:rotate <app-name>".'
        ));

        $strategy->resolve(Context::createDefaultContext());
    }
}
