<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheTagsEvent;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.7.0 - Remove full class
 *
 * @internal
 */
#[Package('checkout')]
#[Group('cache')]
#[Group('store-api')]
class CachedShippingMethodRouteTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const ALL_TAG = 'test-tag';

    private const DATA = [
        'name' => 'test',
        'technicalName' => 'shipping_test',
        'availabilityRule' => ['name' => 'test', 'priority' => 1],
        'deliveryTime' => ['name' => 'test', 'min' => 1, 'max' => 1, 'unit' => 'day'],
    ];

    private const ASSIGNED = [
        'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
    ];

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    #[AfterClass]
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([self::ALL_TAG]);
    }

    #[DataProvider('invalidationProvider')]
    public function testInvalidation(\Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(ShippingMethodRouteCacheTagsEvent::class, static function (ShippingMethodRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(ShippingMethodRoute::class);
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(ShippingMethodRouteCacheTagsEvent::class, $listener);

        $before($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());

        $after($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());
    }

    public static function invalidationProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Cache gets invalidated, if created shipping method assigned to the sales channel' => [
            function (): void {
            },
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if updated shipping method assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('shipping'), 'name' => 'update'];
                $container->get('shipping_method.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if deleted shipping method assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, self::ASSIGNED, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('shipping')];
                $container->get('shipping_method.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets not invalidated, if created shipping method not assigned to the sales channel' => [
            function (): void {
            },
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache gets not invalidated, if updated shipping method not assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('shipping'), 'name' => 'update'];
                $container->get('shipping_method.repository')->update([$update], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache gets invalidated, if deleted shipping method is not assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $shippingMethod = array_merge(self::DATA, ['id' => $ids->get('shipping')]);
                $container->get('shipping_method.repository')->create([$shippingMethod], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('shipping')];
                $container->get('shipping_method.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];
    }
}
