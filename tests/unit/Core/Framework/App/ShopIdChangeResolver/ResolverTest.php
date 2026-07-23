<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ShopIdChangeStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Resolver::class)]
class ResolverTest extends TestCase
{
    private MockObject&ShopIdChangeStrategy $firstStrategy;

    private MockObject&ShopIdChangeStrategy $secondStrategy;

    private Resolver $appUrlChangedResolverStrategy;

    protected function setUp(): void
    {
        $this->firstStrategy = $this->createMock(ShopIdChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(ShopIdChangeStrategy::class);
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

        $this->expectExceptionObject(AppException::shopIdChangeResolveStrategyNotFound('ThirdStrategy'));
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
