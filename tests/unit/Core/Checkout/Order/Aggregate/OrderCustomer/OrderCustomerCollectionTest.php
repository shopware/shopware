<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderCustomer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderCustomerCollection::class)]
class OrderCustomerCollectionTest extends TestCase
{
    public function testGetCustomerIds(): void
    {
        $customerIdA = 'customer-a';
        $customerIdB = 'customer-b';

        $collection = new OrderCustomerCollection([
            $this->createOrderCustomer('order-customer-1', $customerIdA),
            $this->createOrderCustomer('order-customer-2', $customerIdB),
        ]);

        static::assertSame(
            [$customerIdA, $customerIdB],
            array_values($collection->getCustomerIds())
        );
    }

    public function testFilterByCustomerId(): void
    {
        $customerId = 'customer-a';

        $match = $this->createOrderCustomer('order-customer-1', $customerId);
        $other = $this->createOrderCustomer('order-customer-2', 'customer-b');

        $collection = new OrderCustomerCollection([$match, $other]);

        $filtered = $collection->filterByCustomerId($customerId);

        static::assertCount(1, $filtered);
        static::assertSame($match, $filtered->first());
    }

    public function testGetOrdersSkipsNullOrders(): void
    {
        $withOrder = $this->createOrderCustomer('order-customer-1', 'customer-a');
        $withOrder->setOrder($this->createOrder('order-1', new \DateTimeImmutable('2020-01-01')));

        $withoutOrder = $this->createOrderCustomer('order-customer-2', 'customer-b');

        $collection = new OrderCustomerCollection([$withOrder, $withoutOrder]);

        $orders = $collection->getOrders();

        static::assertCount(1, $orders);
        static::assertSame($withOrder->getOrder(), $orders->first());
    }

    public function testGetLastOrderDateReturnsEarliestDate(): void
    {
        $earliest = new \DateTimeImmutable('2019-05-01');
        $latest = new \DateTimeImmutable('2021-09-15');

        $first = $this->createOrderCustomer('order-customer-1', 'customer-a');
        $first->setOrder($this->createOrder('order-1', $latest));

        $second = $this->createOrderCustomer('order-customer-2', 'customer-b');
        $second->setOrder($this->createOrder('order-2', $earliest));

        $collection = new OrderCustomerCollection([$first, $second]);

        static::assertEquals($earliest, $collection->getLastOrderDate());
    }

    public function testGetLastOrderDateReturnsNullWithoutOrders(): void
    {
        $collection = new OrderCustomerCollection([
            $this->createOrderCustomer('order-customer-1', 'customer-a'),
        ]);

        static::assertNull($collection->getLastOrderDate());
    }

    public function testGetApiAlias(): void
    {
        $collection = new OrderCustomerCollection();

        static::assertSame('order_customer_collection', $collection->getApiAlias());
    }

    private function createOrderCustomer(string $id, string $customerId): OrderCustomerEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId($id);
        $orderCustomer->setCustomerId($customerId);

        return $orderCustomer;
    }

    private function createOrder(string $id, \DateTimeImmutable $orderDate): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($id);
        $order->setOrderDate($orderDate);
        $order->setOrderDateTime($orderDate);

        return $order;
    }
}
