<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[CoversClass(MoveShopPermanentlyStrategy::class)]
class MoveShopPermanentlyStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $strategy = new MoveShopPermanentlyStrategy(
            $this->createMock(ManifestFactory::class),
            new StaticEntityRepository([]),
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

        $strategy = new MoveShopPermanentlyStrategy(
            $this->createMock(ManifestFactory::class),
            new StaticEntityRepository([]),
            $appManager,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testRefreshesRegistrationForEveryAppWithSetup(): void
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

        $manifest = ManifestFixture::empty()->withSetup();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->method('createFromApp')->willReturn($manifest);

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function ($app, $passedManifest, $passedContext) use (&$calledApps, $manifest, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($manifest, $passedManifest);
                self::assertSame($context, $passedContext);
            });

        $strategy = new MoveShopPermanentlyStrategy(
            $manifestFactory,
            new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]),
            $appManager,
            $shopIdProvider
        );

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $calledApps);
    }

    public function testSkipsAppsWithoutSetup(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->method('createFromApp')->willReturn(ManifestFixture::empty());

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('refreshRegistration');

        $strategy = new MoveShopPermanentlyStrategy(
            $manifestFactory,
            new StaticEntityRepository([new AppCollection([AppFixture::createAppEntity(name: 'no-setup', id: 'no-setup-id')])]),
            $appManager,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testContinuesWithRemainingAppsAndReportsFailuresTogether(): void
    {
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->method('createFromApp')->willReturn(ManifestFixture::empty()->withSetup());

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function () use (&$calls): void {
                if (++$calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        $strategy = new MoveShopPermanentlyStrategy(
            $manifestFactory,
            new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]),
            $appManager,
            $shopIdProvider
        );

        $this->expectExceptionObject(AppException::reRegistrationFailed(['app-one']));

        $strategy->resolve(Context::createDefaultContext());
    }
}
