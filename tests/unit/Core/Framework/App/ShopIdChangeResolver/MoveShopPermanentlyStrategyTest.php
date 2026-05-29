<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(MoveShopPermanentlyStrategy::class)]
class MoveShopPermanentlyStrategyTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/_fixtures/test-app';

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private AppSecretRotationService&MockObject $appSecretRotationService;

    private ShopIdProvider&MockObject $shopIdProvider;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $this->appRepository = $appRepository;
        $this->appSecretRotationService = $this->createMock(AppSecretRotationService::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
    }

    #[TestDox('getName returns the STRATEGY_NAME constant')]
    public function testGetName(): void
    {
        $strategy = $this->buildStrategy();

        static::assertSame('move-shop-permanently', $strategy->getName());
        static::assertSame(MoveShopPermanentlyStrategy::STRATEGY_NAME, $strategy->getName());
    }

    #[TestDox('getDescription returns a non-empty string')]
    public function testGetDescription(): void
    {
        static::assertNotSame('', $this->buildStrategy()->getDescription());
    }

    #[TestDox('getDecorated throws DecorationPatternException')]
    public function testGetDecoratedThrows(): void
    {
        $strategy = $this->buildStrategy();

        $this->expectExceptionObject(new DecorationPatternException(MoveShopPermanentlyStrategy::class));

        $strategy->getDecorated();
    }

    #[TestDox('resolve returns [] and skips app rotation when the shop id reset clears the change suggestion')]
    public function testResolveShortCircuitsWhenNoShopIdChangeIsSuggested(): void
    {
        $this->shopIdProvider->expects($this->once())->method('reset');
        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willReturn(ShopId::v2('current-shop-id'));
        $this->shopIdProvider->expects($this->never())->method('regenerateAndSetShopId');

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $strategy = $this->buildStrategy();

        static::assertSame([], $strategy->resolve(Context::createDefaultContext()));
    }

    #[TestDox('resolve re-registers each installed app and returns id+name snapshots when a shop id change is suggested')]
    public function testResolveReRegistersAppsWhenShopIdChangeSuggested(): void
    {
        $newShopId = ShopId::v2('new-shop-id');
        $exception = new ShopIdChangeSuggestedException(
            $newShopId,
            new FingerprintComparisonResult([], [], 0),
        );

        $this->shopIdProvider->expects($this->once())->method('reset');
        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willThrowException($exception);
        $this->shopIdProvider->expects($this->once())
            ->method('regenerateAndSetShopId')
            ->with('new-shop-id');

        $app = new AppEntity();
        $app->setUniqueIdentifier('id-a');
        $app->assign(['id' => 'id-a', 'name' => 'test-app']);

        $this->appRepository->searches = [new AppCollection([$app])];

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with('id-a', static::isInstanceOf(Context::class), AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $strategy = $this->buildStrategy();

        $affected = $strategy->resolve(Context::createDefaultContext());

        static::assertSame([['id' => 'id-a', 'name' => 'test-app']], $affected);
    }

    private function buildStrategy(): MoveShopPermanentlyStrategy
    {
        return new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['test-app' => new Filesystem(self::FIXTURE_DIR)]),
            $this->appRepository,
            $this->appSecretRotationService,
            $this->shopIdProvider,
        );
    }
}
