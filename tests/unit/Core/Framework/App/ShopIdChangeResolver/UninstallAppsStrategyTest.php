<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

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

    private UninstallAppsStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appRepository = static::createStub(EntityRepository::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->strategy = new UninstallAppsStrategy(
            $this->appRepository,
            $this->shopIdProvider,
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

    #[TestDox('resolve deletes the shop id, deletes each app, and returns id+name snapshots of every uninstalled app')]
    public function testResolveDeletesAppsAndReturnsSnapshots(): void
    {
        $appA = new AppEntity();
        $appA->setUniqueIdentifier('id-a');
        $appA->assign(['id' => 'id-a', 'name' => 'AppA']);

        $appB = new AppEntity();
        $appB->setUniqueIdentifier('id-b');
        $appB->assign(['id' => 'id-b', 'name' => 'AppB']);

        $this->stubAppRepositorySearch([$appA, $appB]);
        $captureDeletes = $this->captureRepositoryDeletes();

        $this->shopIdProvider->expects($this->once())->method('deleteShopId');

        $affected = $this->strategy->resolve(Context::createDefaultContext());

        static::assertSame([
            ['id' => 'id-a', 'name' => 'AppA'],
            ['id' => 'id-b', 'name' => 'AppB'],
        ], $affected);
        static::assertSame([[['id' => 'id-a']], [['id' => 'id-b']]], $captureDeletes());
    }

    #[TestDox('resolve returns an empty array and skips delete calls when no apps are installed')]
    public function testResolveReturnsEmptyArrayWhenNoApps(): void
    {
        $this->stubAppRepositorySearch([]);
        $captureDeletes = $this->captureRepositoryDeletes();

        $this->shopIdProvider->expects($this->once())->method('deleteShopId');

        $affected = $this->strategy->resolve(Context::createDefaultContext());

        static::assertSame([], $affected);
        static::assertSame([], $captureDeletes());
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
