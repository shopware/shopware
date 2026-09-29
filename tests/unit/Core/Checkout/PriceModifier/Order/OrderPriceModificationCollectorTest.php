<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\PriceModifier\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationEntity;
use Shopware\Core\Checkout\PriceModifier\Order\OrderPriceModificationCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPriceModificationCollector::class)]
class OrderPriceModificationCollectorTest extends TestCase
{
    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testCollectsEmptyCollectionWithoutSearchingWhenCartHasNoOriginalOrder(): void
    {
        // No searches configured: any repository call would throw.
        $repository = StaticEntityRepository::of(OrderPriceModificationCollection::class);
        $data = new CartDataCollection();

        (new OrderPriceModificationCollector($repository))->collect($data, new Cart('token'), $this->context, new CartBehavior());

        $modifications = $data->get(OrderPriceModificationCollector::DATA_KEY);
        static::assertInstanceOf(OrderPriceModificationCollection::class, $modifications);
        static::assertCount(0, $modifications);
    }

    public function testCollectsEmptyCollectionWhenOriginalOrderVersionIsMissing(): void
    {
        $repository = StaticEntityRepository::of(OrderPriceModificationCollection::class);
        $cart = new Cart('token');
        $cart->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct(Uuid::randomHex()));
        $data = new CartDataCollection();

        (new OrderPriceModificationCollector($repository))->collect($data, $cart, $this->context, new CartBehavior());

        $modifications = $data->get(OrderPriceModificationCollector::DATA_KEY);
        static::assertInstanceOf(OrderPriceModificationCollection::class, $modifications);
        static::assertCount(0, $modifications);
    }

    public function testLoadsModificationsOfOriginalOrderInItsVersionSortedByPosition(): void
    {
        $orderId = Uuid::randomHex();
        $orderVersionId = Uuid::randomHex();

        $modification = new OrderPriceModificationEntity();
        $modification->setId(Uuid::randomHex());
        $modifications = new OrderPriceModificationCollection([$modification]);

        $repository = StaticEntityRepository::of(OrderPriceModificationCollection::class, [
            static function (Criteria $criteria, Context $context) use ($orderId, $orderVersionId, $modifications): OrderPriceModificationCollection {
                static::assertEquals([new EqualsFilter('orderId', $orderId)], $criteria->getFilters());
                static::assertEquals([new FieldSorting('position')], $criteria->getSorting());
                static::assertSame($orderVersionId, $context->getVersionId());

                return $modifications;
            },
        ]);

        $cart = new Cart('token');
        $cart->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($orderId));
        $cart->addExtension(OrderConverter::ORIGINAL_VERSION_ID, new IdStruct($orderVersionId));
        $data = new CartDataCollection();

        (new OrderPriceModificationCollector($repository))->collect($data, $cart, $this->context, new CartBehavior());

        static::assertSame($modifications, $data->get(OrderPriceModificationCollector::DATA_KEY));
        static::assertSame([], $repository->searches, 'repository must be queried exactly once');
    }
}
