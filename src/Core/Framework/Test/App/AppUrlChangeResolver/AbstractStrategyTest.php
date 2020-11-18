<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\AppUrlChangeResolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppUrlChangeResolver\AbstractAppUrlChangeStrategy;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\Exception\NoAppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AbstractStrategyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;

    /**
     * @var MockObject|AbstractAppUrlChangeStrategy
     */
    private $firstStrategy;

    /**
     * @var MockObject|AbstractAppUrlChangeStrategy
     */
    private $secondStrategy;

    /**
     * @var Resolver
     */
    private $appUrlChangedResolverStrategy;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function setUp(): void
    {
        $this->firstStrategy = $this->createMock(AbstractAppUrlChangeStrategy::class);
        $this->firstStrategy->method('getName')
            ->willReturn('FirstStrategy');

        $this->secondStrategy = $this->createMock(AbstractAppUrlChangeStrategy::class);
        $this->secondStrategy->method('getName')
            ->willReturn('SecondStrategy');

        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $this->appUrlChangedResolverStrategy = new Resolver([
            $this->firstStrategy,
            $this->secondStrategy,
        ], $this->systemConfigService);
    }

    public function testItThrowsWhenAppUrlChangeIsNotDetected(): void
    {
        $this->firstStrategy->expects(static::never())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        static::expectException(NoAppUrlChangeDetectedException::class);
        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItCallsRightStrategy(): void
    {
        $this->systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

        $this->firstStrategy->expects(static::once())
            ->method('resolve');

        $this->secondStrategy->expects(static::never())
            ->method('resolve');

        $this->appUrlChangedResolverStrategy->resolve('FirstStrategy', Context::createDefaultContext());
    }

    public function testItThrowsOnUnknownStrategy(): void
    {
        $this->systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

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
