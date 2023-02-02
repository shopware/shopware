<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppUrlChangeResolver\AbstractAppUrlChangeStrategy;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AbstractStrategyTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MockObject&AbstractAppUrlChangeStrategy $firstStrategy;

    private MockObject&AbstractAppUrlChangeStrategy $secondStrategy;

    private Resolver $appUrlChangedResolverStrategy;

    public function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AbstractAppUrlChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AbstractAppUrlChangeStrategy::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->appUrlChangedResolverStrategy = new Resolver([
            $this->firstStrategy,
            $this->secondStrategy,
        ]);
    }

    public function testItCallsRightStrategy(): void
    {
        $this->firstStrategy->expects(static::once())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->firstStrategy->expects(static::never())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        static::expectException(AppUrlChangeStrategyNotFoundException::class);
        $this->appUrlChangedResolverStrategy->resolve('ThirdStrategy', Context::createDefaultContext());
    }

    public function testGetAvailableStrategies(): void
    {
        $this->firstStrategy->expects(static::once())
            ->method('getDescription')
            ->willReturn('first description');

        $this->secondStrategy->expects(static::once())
            ->method('getDescription')
            ->willReturn('second description');

        static::assertEquals([
            'FirstStrategy' => 'first description',
            'SecondStrategy' => 'second description',
        ], $this->appUrlChangedResolverStrategy->getAvailableStrategies());
    }
}
