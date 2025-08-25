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

        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('809c1844f4734243b6aa04aba860cd45 test tag viet nam da nang 5000 street 123 test address 124 firstname lastname test@example.com 12345 1', $document['text']);
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

        $connection->method('fetchAllAssociative')->with(
            '
            SELECT LOWER(HEX(order.id)) as id,
                   GROUP_CONCAT(DISTINCT tag.name SEPARATOR " ") as tags,
                   GROUP_CONCAT(DISTINCT country_translation.name SEPARATOR " ") as country,
                   GROUP_CONCAT(DISTINCT order_address.city SEPARATOR " ") as city,
                   GROUP_CONCAT(DISTINCT order_address.street SEPARATOR " ") as street,
                   GROUP_CONCAT(DISTINCT order_address.zipcode SEPARATOR " ") as zipcode,
                   GROUP_CONCAT(DISTINCT order_address.phone_number SEPARATOR " ") as phone_number,
                   GROUP_CONCAT(DISTINCT order_address.additional_address_line1 SEPARATOR " ") as additional_address_line1,
                   GROUP_CONCAT(DISTINCT order_address.additional_address_line2 SEPARATOR " ") as additional_address_line2,
                   GROUP_CONCAT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(document.config, "$.documentNumber")) SEPARATOR " ") as documentNumber,
                   order_customer.first_name,
                   order_customer.last_name,
                   order_customer.email,
                   order_customer.company,
                   order_customer.customer_number,
                   `order`.order_number,
                   `order`.amount_total,
                   order_delivery.tracking_codes
            FROM `order`
                LEFT JOIN order_customer
                    ON `order`.id = order_customer.order_id AND order_customer.order_version_id = :versionId
                LEFT JOIN order_address
                    ON `order`.id = order_address.order_id AND order_address.order_version_id = :versionId
                LEFT JOIN country
                    ON order_address.country_id = country.id
                LEFT JOIN country_translation
                    ON country.id = country_translation.country_id
                LEFT JOIN order_tag
                    ON `order`.id = order_tag.order_id AND order_tag.order_version_id = :versionId
                LEFT JOIN tag
                    ON order_tag.tag_id = tag.id
                LEFT JOIN order_delivery
                    ON `order`.id = order_delivery.order_id AND order_delivery.order_version_id = :versionId
                LEFT JOIN document
                    ON `order`.id = document.order_id
            WHERE order.id IN (:ids) AND `order`.version_id = :versionId
            GROUP BY order.id
        ',
            [
                'ids' => Uuid::fromHexToBytesList(['809c1844f4734243b6aa04aba860cd45']),
                'versionId' => Uuid::fromHexToBytes('0fa91ce3e96a4bc2be4bd9ce752c3425'),
            ]
        )->willReturn(
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
