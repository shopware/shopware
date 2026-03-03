<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
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
use Shopware\Elasticsearch\Admin\Indexer\PropertyGroupAdminSearchIndexer;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

/**
 * @internal
 */
#[CoversClass(PropertyGroupAdminSearchIndexer::class)]
class PropertyGroupAdminSearchIndexerTest extends TestCase
{
    private PropertyGroupAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new PropertyGroupAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new PropertyGroupAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            100
        );

        $id = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('property_group_translation', [
                    new EntityWriteResult(['propertyGroupId' => $id], ['name' => 'PG'], 'property_group_translation', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$id], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(PropertyGroupDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('property-group-listing', $this->searchIndexer->getName());
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
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'property_group',
                1,
                new EntityCollection([$propertyGroup]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new PropertyGroupAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $repository,
            $this->createMock(ElasticsearchFieldBuilder::class),
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

        $indexer = new PropertyGroupAdminSearchIndexer(
            $connection,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('property group 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertTrue($document['filterable']);
        static::assertIsArray($document['name']);
    }

    private function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);

        $languageId = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'name' => 'Property group',
                    'translatedNames' => json_encode([
                        ['languageId' => $languageId, 'name' => 'Property group'],
                    ]),
                    'filterable' => 1,
                    'createdAt' => '2024-01-01 00:00:00.000',
                ],
            ],
        );

        return $connection;
    }
}
