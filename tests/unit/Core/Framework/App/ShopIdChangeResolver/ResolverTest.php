<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AbstractShopIdChangeStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(Resolver::class)]
class ResolverTest extends TestCase
{
    private MockObject&AbstractShopIdChangeStrategy $firstStrategy;

    private MockObject&AbstractShopIdChangeStrategy $secondStrategy;

    private Resolver $appUrlChangedResolverStrategy;

    protected function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AbstractShopIdChangeStrategy::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->appUrlChangedResolverStrategy = new Resolver([
            $this->firstStrategy,
            $this->secondStrategy,
        ]);
    }

    public function testItCallsRightStrategy(): void
    {
        $this->firstStrategy->expects($this->once())
            ->method('resolve');

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects($this->never())
            ->method('resolve');

        $this->secondStrategy->expects($this->never())
            ->method('resolve');

        $this->expectException(ShopIdChangeStrategyNotFoundException::class);
        $this->expectExceptionMessage('Shop ID change resolver with name "ThirdStrategy" not found.');
        $this->appUrlChangedResolverStrategy->resolve('ThirdStrategy', Context::createDefaultContext());
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
        ], $this->appUrlChangedResolverStrategy->getAvailableStrategies());
    }
}
