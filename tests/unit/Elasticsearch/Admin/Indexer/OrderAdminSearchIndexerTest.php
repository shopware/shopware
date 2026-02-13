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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

        static::assertEquals($result['total'], $data['total']);
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
        static::assertSame('aabbccdd11223344556677889900aabb', $document['stateId']);
        static::assertSame('bbccddee22334455667788990011aabb', $document['salesChannelId']);
        static::assertIsArray($document['tags']);
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
                ],
            ],
        );

        return $connection;
    }
}
