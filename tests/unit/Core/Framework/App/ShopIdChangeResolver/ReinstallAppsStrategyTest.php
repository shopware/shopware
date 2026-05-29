<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ReinstallAppsStrategy::class)]
class ReinstallAppsStrategyTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/_fixtures/test-app';

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private AppSecretRotationService&MockObject $appSecretRotationService;

    private ShopIdProvider&MockObject $shopIdProvider;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $this->appRepository = $appRepository;
        $this->appSecretRotationService = $this->createMock(AppSecretRotationService::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->eventDispatcher = new EventDispatcher();
    }

    #[TestDox('getName returns the STRATEGY_NAME constant')]
    public function testGetName(): void
    {
        $strategy = $this->buildStrategy();

        static::assertSame('reinstall-apps', $strategy->getName());
        static::assertSame(ReinstallAppsStrategy::STRATEGY_NAME, $strategy->getName());
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

        $this->expectExceptionObject(new DecorationPatternException(ReinstallAppsStrategy::class));

        $strategy->getDecorated();
    }

    #[TestDox('resolve deletes the shop id and returns [] when no apps are installed')]
    public function testResolveDeletesShopIdAndReturnsEmptyArrayWhenNoApps(): void
    {
        $this->shopIdProvider->expects($this->once())->method('deleteShopId');

        $this->appSecretRotationService->expects($this->never())->method('rotateNow');

        $dispatchedEvents = [];
        $this->eventDispatcher->addListener(
            AppInstalledEvent::class,
            static function (AppInstalledEvent $event) use (&$dispatchedEvents): void {
                $dispatchedEvents[] = $event;
            }
        );

        $strategy = $this->buildStrategy();

        static::assertSame([], $strategy->resolve(Context::createDefaultContext()));
        static::assertSame([], $dispatchedEvents);
    }

    #[TestDox('resolve re-registers each installed app, dispatches AppInstalledEvent per app, and returns id+name snapshots')]
    public function testResolveReRegistersAppsAndDispatchesEvents(): void
    {
        $this->shopIdProvider->expects($this->once())->method('deleteShopId');

        $app = new AppEntity();
        $app->setUniqueIdentifier('id-a');
        $app->assign(['id' => 'id-a', 'name' => 'test-app']);

        $this->appRepository->searches = [new AppCollection([$app])];

        $this->appSecretRotationService->expects($this->once())
            ->method('rotateNow')
            ->with('id-a', static::isInstanceOf(Context::class), AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $dispatchedEvents = [];
        $this->eventDispatcher->addListener(
            AppInstalledEvent::class,
            static function (AppInstalledEvent $event) use (&$dispatchedEvents): void {
                $dispatchedEvents[] = $event;
            }
        );

        $strategy = $this->buildStrategy();

        $affected = $strategy->resolve(Context::createDefaultContext());

        static::assertSame([['id' => 'id-a', 'name' => 'test-app']], $affected);
        static::assertCount(1, $dispatchedEvents);
        static::assertSame('id-a', $dispatchedEvents[0]->getApp()->getId());
    }

    private function buildStrategy(): ReinstallAppsStrategy
    {
        return new ReinstallAppsStrategy(
            new StaticSourceResolver(['test-app' => new Filesystem(self::FIXTURE_DIR)]),
            $this->appRepository,
            $this->appSecretRotationService,
            $this->shopIdProvider,
            $this->eventDispatcher,
        );
    }
}
