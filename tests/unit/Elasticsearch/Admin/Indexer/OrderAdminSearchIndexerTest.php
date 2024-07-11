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
 * @package services-settings
 *
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

        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('809c1844f4734243b6aa04aba860cd45 test tag viet nam da nang 5000 street 123 test address 124 firstname lastname test@example.com 12345 1', $document['text']);
    }

    private function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'tags' => 'test Tag',
                    'country' => 'Viet Nam',
                    'city' => 'Da Nang',
                    'zipcode' => '5000',
                    'street' => 'street',
                    'phone_number' => '123',
                    'additional_address_line1' => 'test',
                    'additional_address_line2' => 'address',
                    'document_number' => '124',
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'test@example.com',
                    'company' => null,
                    'customer_number' => '12345',
                    'order_number' => '12345',
                    'amount_total' => '1',
                ],
            ],
        );

        return $connection;
    }
}
