<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheTagsEvent;
use Shopware\Core\Checkout\Shipping\SalesChannel\CachedShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 * @group store-api
 */
class CachedShippingMethodRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const ALL_TAG = 'test-tag';

    private const DATA = [
        'name' => 'test',
        'availabilityRule' => ['name' => 'test', 'priority' => 1],
        'deliveryTime' => ['name' => 'test', 'min' => 1, 'max' => 1, 'unit' => 'day'],
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
            ->addListener(ShippingMethodRouteCacheTagsEvent::class, static function (ShippingMethodRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(ShippingMethodRoute::class);
        static::assertInstanceOf(CachedShippingMethodRoute::class, $route);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(ShippingMethodRouteCacheTagsEvent::class, $listener);

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

        yield 'Cache gets invalidated, if created shipping method assigned to the sales channel' => [
            function (): void {
            },
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if updated shipping method assigned to the sales channel' => [
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            function () use ($ids): void {
                $update = ['id' => $ids->get('shipping'), 'name' => 'update'];
                $this->getContainer()->get('shipping_method.repository')->update([$update], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if deleted shipping method assigned to the sales channel' => [
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            function () use ($ids): void {
                $delete = ['id' => $ids->get('shipping')];
                $this->getContainer()->get('shipping_method.repository')->delete([$delete], $ids->getContext());
            },
            2,
        ];

        yield 'Cache gets not invalidated, if created shipping method not assigned to the sales channel' => [
            function (): void {
            },
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            1,
        ];

        yield 'Cache gets not invalidated, if updated shipping method not assigned to the sales channel' => [
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            function () use ($ids): void {
                $update = ['id' => $ids->get('shipping'), 'name' => 'update'];
                $this->getContainer()->get('shipping_method.repository')->update([$update], $ids->getContext());
            },
            1,
        ];

        yield 'Cache gets invalidated, if deleted shipping method is not assigned to the sales channel' => [
            function () use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $this->getContainer()->get('shipping_method.repository')->create([$shippingMethod], $ids->getContext());
            },
            function () use ($ids): void {
                $delete = ['id' => $ids->get('shipping')];
                $this->getContainer()->get('shipping_method.repository')->delete([$delete], $ids->getContext());
            },
            2,
        ];
    }
}
