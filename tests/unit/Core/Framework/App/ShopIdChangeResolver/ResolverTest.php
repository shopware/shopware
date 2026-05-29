<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Event\ShopIdResolvedEvent;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AbstractShopIdChangeStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
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

    private Resolver $appUrlChangedResolverStrategy;

    protected function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->eventDispatcher = new EventDispatcher();

        $this->appUrlChangedResolverStrategy = new Resolver(
            [$this->firstStrategy, $this->secondStrategy],
            $this->eventDispatcher,
        );
    }

    #[TestDox('resolve runs the matching strategy and dispatches ShopIdResolvedEvent with the strategy name and affected apps')]
    public function testItCallsRightStrategyAndDispatchesEvent(): void
    {
        $context = Context::createDefaultContext();
        $affected = [
            ['id' => 'id-a', 'name' => 'AppA'],
            ['id' => 'id-b', 'name' => 'AppB'],
        ];

        $this->firstStrategy->expects($this->once())
            ->method('resolve')
            ->with($context)
            ->willReturn($affected);

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $dispatched = [];
        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function (ShopIdResolvedEvent $event) use (&$dispatched): void {
                $dispatched[] = $event;
            }
        );

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', $context);

        static::assertCount(1, $dispatched);
        static::assertSame('FirstStrategy', $dispatched[0]->strategyName);
        static::assertSame($affected, $dispatched[0]->affectedApps);
        static::assertSame($context, $dispatched[0]->context);
    }

    #[TestDox('resolve dispatches ShopIdResolvedEvent with empty affectedApps when the strategy reports no work')]
    public function testItDispatchesEventEvenWhenStrategyAffectsNoApps(): void
    {
        $this->firstStrategy->method('resolve')->willReturn([]);

        $dispatched = 0;
        $affectedCount = null;
        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function (ShopIdResolvedEvent $event) use (&$dispatched, &$affectedCount): void {
                ++$dispatched;
                $affectedCount = \count($event->affectedApps);
            }
        );

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());

        static::assertSame(1, $dispatched);
        static::assertSame(0, $affectedCount);
    }

    #[TestDox('resolve throws and does not dispatch when no strategy matches')]
    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects($this->never())
            ->method('resolve');

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $dispatched = 0;
        $this->eventDispatcher->addListener(
            ShopIdResolvedEvent::class,
            static function () use (&$dispatched): void {
                ++$dispatched;
            }
        );

        $this->expectExceptionObject(AppException::shopIdChangeResolveStrategyNotFound('ThirdStrategy'));

        try {
            $this->appUrlChangedResolverStrategy->resolve('ThirdStrategy', Context::createDefaultContext());
        } finally {
            static::assertSame(0, $dispatched);
        }
    }

    #[TestDox('getAvailableStrategies returns the strategy descriptions keyed by strategy name')]
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
        ], $this->appUrlChangedResolverStrategy->getAvailableStrategies());
    }
}
