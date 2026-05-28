<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppSilentlyUninstalledEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(UninstallAppsStrategy::class)]
class UninstallAppsStrategyTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<AppCollection>
     */
    private EntityRepository&Stub $appRepository;

    private ShopIdProvider&MockObject $shopIdProvider;

    private EventDispatcher $eventDispatcher;

    private UninstallAppsStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appRepository = static::createStub(EntityRepository::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->strategy = new UninstallAppsStrategy(
            $this->appRepository,
            $this->shopIdProvider,
            $this->eventDispatcher,
        );
    }

    #[TestDox('getName returns the STRATEGY_NAME constant')]
    public function testGetName(): void
    {
        static::assertSame('uninstall-apps', $this->strategy->getName());
        static::assertSame(UninstallAppsStrategy::STRATEGY_NAME, $this->strategy->getName());
    }

    #[TestDox('getDescription returns a non-empty string')]
    public function testGetDescription(): void
    {
        static::assertNotSame('', $this->strategy->getDescription());
    }

    #[TestDox('getDecorated throws DecorationPatternException')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(UninstallAppsStrategy::class));

        $this->strategy->getDecorated();
    }

    #[TestDox('resolve deletes the shop id once, dispatches AppSilentlyUninstalledEvent per app, and deletes each app via the repository')]
    public function testResolveDeletesShopIdDispatchesEventsAndDeletesApps(): void
    {
        $appA = new AppEntity();
        $appA->setUniqueIdentifier('id-a');
        $appA->assign(['id' => 'id-a', 'name' => 'AppA']);

        $appB = new AppEntity();
        $appB->setUniqueIdentifier('id-b');
        $appB->assign(['id' => 'id-b', 'name' => 'AppB']);

        $this->stubAppRepositorySearch([$appA, $appB]);
        $captureDeletes = $this->captureRepositoryDeletes();

        $dispatchedAppNames = [];
        $this->eventDispatcher->addListener(
            AppSilentlyUninstalledEvent::class,
            static function (AppSilentlyUninstalledEvent $event) use (&$dispatchedAppNames): void {
                $dispatchedAppNames[] = $event->app->getName();
            }
        );

        $this->shopIdProvider->expects($this->once())->method('deleteShopId');

        $context = Context::createDefaultContext();
        $this->strategy->resolve($context);

        static::assertSame(['AppA', 'AppB'], $dispatchedAppNames);
        static::assertSame([[['id' => 'id-a']], [['id' => 'id-b']]], $captureDeletes());
    }

    /**
     * @param list<AppEntity> $apps
     */
    private function stubAppRepositorySearch(array $apps): void
    {
        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new AppCollection($apps));
        $this->appRepository->method('search')->willReturn($result);
    }

    /**
     * @return \Closure(): list<array<int, array{id: string}>>
     */
    private function captureRepositoryDeletes(): \Closure
    {
        /** @var list<array<int, array{id: string}>> $payloads */
        $payloads = [];
        $writeResult = static::createStub(EntityWrittenContainerEvent::class);
        $this->appRepository->method('delete')
            ->willReturnCallback(static function (array $ids) use (&$payloads, $writeResult): EntityWrittenContainerEvent {
                /** @var array<int, array{id: string}> $ids */
                $payloads[] = $ids;

                return $writeResult;
            });

        return function () use (&$payloads) {
            return $payloads;
        };
    }
}
