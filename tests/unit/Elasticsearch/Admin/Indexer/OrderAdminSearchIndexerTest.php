<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\OrderAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(OrderAdminSearchIndexer::class)]
class OrderAdminSearchIndexerTest extends TestCase
{
    private OrderAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new OrderAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );
    }

    public function testGetEntity(): void
    {
        static::assertSame(OrderDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('order-listing', $this->searchIndexer->getName());
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->searchIndexer->getDecorated();
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createMock(EntityRepository::class);
        $order = new OrderEntity();
        $order->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'order',
                1,
                new EntityCollection([$order]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new OrderAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $repository,
            100
        );

        $result = [
            'total' => 1,
            'hits' => [
                ['id' => '809c1844f4734243b6aa04aba860cd45'],
            ],
        ];

        $data = $indexer->globalData($result, $context);

        static::assertSame($result['total'], $data['total']);
    }

    public function testFetching(): void
    {
        $connection = $this->getConnection();

        $indexer = new OrderAdminSearchIndexer(
            $connection,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('10001 test@example.com firstname lastname 12345 test tag viet nam da nang street 5000 123 test address 124 99.99 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame('10001', $document['orderNumber']);
        static::assertSame(99.99, $document['amountTotal']);
        static::assertArrayHasKey('orderDate', $document);
        static::assertArrayHasKey('orderDateTime', $document);
        static::assertSame('aabbccdd11223344556677889900aabb', $document['stateId']);
        static::assertIsArray($document['stateMachineState']);
        static::assertSame('aabbccdd11223344556677889900aabb', $document['stateMachineState']['id']);
        static::assertSame(1, $document['stateMachineState']['_count']);
        static::assertSame('bbccddee22334455667788990011aabb', $document['salesChannelId']);
        static::assertIsArray($document['tags']);
        static::assertIsArray($document['billingAddress']);
        static::assertSame('aa00112233445566778899aabbccddee', $document['billingAddress']['id']);
        static::assertSame('bb00112233445566778899aabbccddee', $document['billingAddress']['countryId']);
        static::assertIsArray($document['orderCustomer']);
        static::assertSame('cc00112233445566778899aabbccddee', $document['orderCustomer']['id']);
        static::assertSame('dd00112233445566778899aabbccddee', $document['orderCustomer']['customer']['id']);
        static::assertSame('ee00112233445566778899aabbccddee', $document['orderCustomer']['customer']['groupId']);
        static::assertSame('CUST-001', $document['orderCustomer']['customer']['customerNumber']);
        static::assertIsArray($document['lineItems']);
        static::assertCount(1, $document['lineItems']);
        static::assertSame('ff00112233445566778899aabbccddee', $document['lineItems'][0]['id']);
        static::assertSame('1100112233445566778899aabbccddee', $document['lineItems'][0]['productId']);
        static::assertSame('PROMO10', $document['lineItems'][0]['payload']['code']);
        static::assertIsArray($document['primaryOrderTransaction']);
        static::assertSame('2233445566778899aabb00112233ccdd', $document['primaryOrderTransaction']['id']);
        static::assertSame('3344556677889900aabb00112233ccdd', $document['primaryOrderTransaction']['stateMachineState']['id']);
        static::assertSame(1, $document['primaryOrderTransaction']['stateMachineState']['_count']);
        static::assertSame('4455667788990011aabb00112233ccdd', $document['primaryOrderTransaction']['paymentMethodId']);
        static::assertIsArray($document['primaryOrderDelivery']);
        static::assertSame('5566778899001122aabb00112233ccdd', $document['primaryOrderDelivery']['id']);
        static::assertSame('6677889900112233aabb00112233ccdd', $document['primaryOrderDelivery']['stateMachineState']['id']);
        static::assertSame(1, $document['primaryOrderDelivery']['stateMachineState']['_count']);
        static::assertSame('7788990011223344aabb00112233ccdd', $document['primaryOrderDelivery']['shippingMethodId']);
        static::assertSame('8899001122334455aabb00112233ccdd', $document['primaryOrderDelivery']['shippingOrderAddress']['countryId']);
        static::assertIsArray($document['documents']);
        static::assertCount(1, $document['documents']);
        static::assertSame('ff1122334455667788990011aabbccdd', $document['documents'][0]['id']);
        static::assertSame(1, $document['documents'][0]['_count']);
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new OrderAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );

        $orderId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('order', [
                    new EntityWriteResult($orderId, ['amountTotal' => 123.45], 'order', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$orderId], $indexer->getUpdatedIds($event));
    }

    private function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'tags' => 'test Tag',
                    'tagIds' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
                    'country' => 'Viet Nam',
                    'city' => 'Da Nang',
                    'zipcode' => '5000',
                    'street' => 'street',
                    'phone_number' => '123',
                    'additional_address_line1' => 'test',
                    'additional_address_line2' => 'address',
                    'documentNumber' => '124',
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'test@example.com',
                    'company' => null,
                    'customer_number' => '12345',
                    'order_number' => '10001',
                    'amount_total' => '99.99',
                    'order_date_time' => '2024-01-15 10:30:00.000',
                    'stateId' => 'aabbccdd11223344556677889900aabb',
                    'salesChannelId' => 'bbccddee22334455667788990011aabb',
                    'affiliateCode' => null,
                    'campaignCode' => null,
                    'createdAt' => '2024-01-01 00:00:00.000',
                    'tracking_codes' => null,
                    'documentIds' => 'ff1122334455667788990011aabbccdd',
                    'billingAddressId' => 'aa00112233445566778899aabbccddee',
                    'billingAddressCountryId' => 'bb00112233445566778899aabbccddee',
                    'orderCustomerId' => 'cc00112233445566778899aabbccddee',
                    'customerId' => 'dd00112233445566778899aabbccddee',
                    'customerGroupId' => 'ee00112233445566778899aabbccddee',
                    'liveCustomerNumber' => 'CUST-001',
                    'lineItems' => json_encode([
                        ['id' => 'ff00112233445566778899aabbccddee', 'productId' => '1100112233445566778899aabbccddee', 'code' => 'PROMO10'],
                    ]),
                    'primaryTransactionId' => '2233445566778899aabb00112233ccdd',
                    'primaryTransactionStateId' => '3344556677889900aabb00112233ccdd',
                    'primaryTransactionPaymentMethodId' => '4455667788990011aabb00112233ccdd',
                    'primaryDeliveryId' => '5566778899001122aabb00112233ccdd',
                    'primaryDeliveryStateId' => '6677889900112233aabb00112233ccdd',
                    'primaryDeliveryShippingMethodId' => '7788990011223344aabb00112233ccdd',
                    'primaryDeliveryCountryId' => '8899001122334455aabb00112233ccdd',
                ],
            ],
        );

        return $connection;
    }
}
