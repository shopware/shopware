<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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
use Shopware\Elasticsearch\Admin\Indexer\CustomerAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(CustomerAdminSearchIndexer::class)]
class CustomerAdminSearchIndexerTest extends TestCase
{
    private CustomerAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new CustomerAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new CustomerAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            100
        );

        $customerId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('customer', [
                    new EntityWriteResult($customerId, ['firstName' => 'Jane'], 'customer', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
                new EntityWrittenEvent('customer_address', [
                    new EntityWriteResult(Uuid::randomHex(), ['firstName' => 'A'], 'customer_address', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$customerId], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(CustomerDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('customer-listing', $this->searchIndexer->getName());
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
        $customer = new CustomerEntity();
        $customer->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'customer',
                1,
                new EntityCollection([$customer]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new CustomerAdminSearchIndexer(
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

        $indexer = new CustomerAdminSearchIndexer(
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
        static::assertSame('firstname lastname test@example.com 12345 test tag viet nam da nang street 123 test test 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertTrue($document['active']);
        static::assertSame('test@example.com', $document['email']);
        static::assertSame('firstname', $document['firstName']);
        static::assertSame('lastname', $document['lastName']);
        static::assertSame('12345', $document['customerNumber']);
        static::assertIsArray($document['defaultBillingAddress']);
        static::assertSame('bb11223344556677889900aabbccddee', $document['defaultBillingAddress']['id']);
        static::assertSame('cc11223344556677889900aabbccddee', $document['defaultBillingAddress']['countryId']);
        static::assertIsArray($document['defaultShippingAddress']);
        static::assertSame('dd11223344556677889900aabbccddee', $document['defaultShippingAddress']['id']);
        static::assertSame('ee11223344556677889900aabbccddee', $document['defaultShippingAddress']['countryId']);
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
                    'address_first_name' => null,
                    'address_last_name' => null,
                    'address_company' => null,
                    'city' => 'Da Nang',
                    'zipcode' => null,
                    'street' => 'street',
                    'phone_number' => '123',
                    'additional_address_line1' => 'test',
                    'additional_address_line2' => 'test',
                    'first_name' => 'firstname',
                    'last_name' => 'lastname',
                    'email' => 'test@example.com',
                    'company' => null,
                    'customer_number' => '12345',
                    'active' => 1,
                    'affiliateCode' => null,
                    'campaignCode' => null,
                    'groupId' => 'aabbccdd11223344556677889900aabb',
                    'salutationId' => null,
                    'boundSalesChannelId' => null,
                    'requestedGroupId' => null,
                    'defaultBillingAddressId' => 'bb11223344556677889900aabbccddee',
                    'defaultBillingAddressCountryId' => 'cc11223344556677889900aabbccddee',
                    'defaultShippingAddressId' => 'dd11223344556677889900aabbccddee',
                    'defaultShippingAddressCountryId' => 'ee11223344556677889900aabbccddee',
                    'createdAt' => '2024-01-01 00:00:00.000',
                ],
            ],
        );

        return $connection;
    }
}
