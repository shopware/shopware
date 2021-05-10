<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Event\CurrencyRouteCacheTagsEvent;
use Shopware\Core\System\Currency\SalesChannel\CachedCurrencyRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 * @group store-api
 */
class CachedCurrencyRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const ALL_TAG = 'test-tag';

    private const CURRENCY = [
        'name' => 'test',
        'factor' => 1,
        'isoCode' => 'aa',
        'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
        'shortName' => 'test',
        'symbol' => '€',
    ];

    private const ASSIGNED = [
        'salesChannels' => [['id' => Defaults::SALES_CHANNEL]],
    ];

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([self::ALL_TAG]);
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(\Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(CurrencyRouteCacheTagsEvent::class, static function (CurrencyRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(CurrencyRoute::class);
        static::assertInstanceOf(CachedCurrencyRoute::class, $route);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(CurrencyRouteCacheTagsEvent::class, $listener);

        $before();

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());

        $after();

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());
    }

    public function invalidationProvider()
    {
        $ids = new IdsCollection();

        yield 'Cache gets invalidated, if created currency assigned to the sales channel' => [
            function (): void {
            },
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, self::ASSIGNED, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if updated currency assigned to the sales channel' => [
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, self::ASSIGNED, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            function () use ($ids): void {
                $update = ['id' => $ids->get('currency'), 'name' => 'update'];
                $this->getContainer()->get('currency.repository')->update([$update], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if deleted currency assigned to the sales channel' => [
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, self::ASSIGNED, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            function () use ($ids): void {
                $delete = ['id' => $ids->get('currency')];
                $this->getContainer()->get('currency.repository')->delete([$delete], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets not invalidated, if created currency not assigned to the sales channel' => [
            function (): void {
            },
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            1,
        ];

        yield 'Cache gets not invalidated, if updated currency not assigned to the sales channel' => [
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            function () use ($ids): void {
                $update = ['id' => $ids->get('currency'), 'name' => 'update'];
                $this->getContainer()->get('currency.repository')->update([$update], $ids->getContext());
            },
            1,
        ];

        yield 'Cache gets invalidated, if deleted currency is not assigned to the sales channel' => [
            function () use ($ids): void {
                $currency = array_merge(self::CURRENCY, ['id' => $ids->get('currency')]);
                $this->getContainer()->get('currency.repository')->create([$currency], $ids->getContext());
            },
            function () use ($ids): void {
                $delete = ['id' => $ids->get('currency')];
                $this->getContainer()->get('currency.repository')->delete([$delete], $ids->getContext());
            },
            2,
        ];
    }
}
