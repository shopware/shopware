<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Event\ShopIdResolvedEvent;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AbstractShopIdChangeStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(Resolver::class)]
class ResolverTest extends TestCase
{
    private MockObject&AbstractShopIdChangeStrategy $firstStrategy;

    private MockObject&AbstractShopIdChangeStrategy $secondStrategy;

    private EventDispatcher $eventDispatcher;

    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->firstStrategy->method('getName')->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->secondStrategy->method('getName')->willReturn('SecondStrategy');

        $this->eventDispatcher = new EventDispatcher();

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection([
                $this->createApp('app-1', 'SwagTheme'),
                $this->createApp('app-2', 'SwagPlain'),
            ]),
        ]);

        $this->resolver = new Resolver(
            [$this->firstStrategy, $this->secondStrategy],
            $appRepository,
            $this->eventDispatcher,
        );
    }

    public function testItCallsRightStrategy(): void
    {
        $this->firstStrategy->expects($this->once())->method('resolve');
        $this->secondStrategy->expects($this->never())->method('resolve');

        $this->resolver->resolve('FirstStrategy', Context::createDefaultContext());
    }

    #[TestDox('Dispatches ShopIdResolvedEvent with the strategy name and a snapshot of all installed apps')]
    public function testItDispatchesShopIdResolvedEventWithAppSnapshot(): void
    {
        $captured = null;
        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function (ShopIdResolvedEvent $event) use (&$captured): void {
                $captured = $event;
            }
        );

        $context = Context::createDefaultContext();
        $this->resolver->resolve('FirstStrategy', $context);

        static::assertInstanceOf(ShopIdResolvedEvent::class, $captured);
        static::assertSame('FirstStrategy', $captured->strategyName);
        static::assertSame($context, $captured->context);
        static::assertSame([
            ['id' => 'app-1', 'name' => 'SwagTheme'],
            ['id' => 'app-2', 'name' => 'SwagPlain'],
        ], $captured->affectedApps);
    }

    #[TestDox('Snapshots the apps before the strategy runs, then dispatches the event afterwards')]
    public function testItSnapshotsAppsBeforeRunningTheStrategy(): void
    {
        $order = [];

        $this->firstStrategy->method('resolve')->willReturnCallback(
            static function () use (&$order): void {
                $order[] = 'resolve';
            }
        );

        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function () use (&$order): void {
                $order[] = 'event';
            }
        );

        $this->resolver->resolve('FirstStrategy', Context::createDefaultContext());

        static::assertSame(['resolve', 'event'], $order);
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects($this->never())->method('resolve');
        $this->secondStrategy->expects($this->never())->method('resolve');

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->expectExceptionObject(AppException::shopIdChangeResolveStrategyNotFound('ThirdStrategy'));

        try {
            $this->resolver->resolve('ThirdStrategy', Context::createDefaultContext());
        } finally {
            static::assertFalse($eventDispatched);
        }
    }

    public function testGetAvailableStrategies(): void
    {
        $this->firstStrategy->expects($this->once())
            ->method('getDescription')
            ->willReturn('first description');

        $this->secondStrategy->expects($this->once())
            ->method('getDescription')
            ->willReturn('second description');

        static::assertSame([
            'FirstStrategy' => 'first description',
            'SecondStrategy' => 'second description',
        ], $this->resolver->getAvailableStrategies());
    }

    private function createApp(string $id, string $name): AppEntity
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier($id);
        $app->assign(['id' => $id, 'name' => $name]);

        return $app;
    }
}
