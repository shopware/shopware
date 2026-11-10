<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAdminSearchIndexer::class)]
class ProductAdminSearchIndexerTest extends TestCase
{
    private ProductAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );
    }

    public function testGetEntity(): void
    {
        static::assertSame(ProductDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('product-listing', $this->searchIndexer->getName());
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->searchIndexer->getDecorated();
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = static::createStub(EntityRepository::class);
        $product = new ProductEntity();
        $product->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'product',
                1,
                new EntityCollection([$product]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            $repository,
            static::createStub(ElasticsearchFieldBuilder::class),
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

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('tag sw1000299 4572324423421 m-100 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame('sw1000299 keywords', $document['textBoosted']);
        static::assertSame(['product'], $document['completion']);
        static::assertSame('SW1000299', $document['productNumber']);
        static::assertTrue($document['active']);
        static::assertSame(10, $document['sales']);
        static::assertSame(100, $document['stock']);
        static::assertIsArray($document['name']);
        static::assertIsArray($document['tags']);
        static::assertSame('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', $document['tags'][0]['id']);
        static::assertIsArray($document['media']);
        static::assertCount(1, $document['media']);
        static::assertSame('aabb11223344556677889900aabbccdd', $document['media'][0]['id']);
        static::assertSame([
            ['id' => 'c1c2c3c4c5c6c7c8c9c0c1c2c3c4c5c6', '_count' => 1],
            ['id' => 'd1d2d3d4d5d6d7d8d9d0d1d2d3d4d5d6', '_count' => 1],
        ], $document['documents']);
        static::assertIsArray($document['price']);
        static::assertArrayHasKey('c_b7d2554b0ce847cd82f3ac9bd1c0dfca', $document['price']);
        static::assertSame(100.0, $document['price']['c_b7d2554b0ce847cd82f3ac9bd1c0dfca']['gross']);
        static::assertSame(84.03, $document['price']['c_b7d2554b0ce847cd82f3ac9bd1c0dfca']['net']);
    }

    public function testFetchingAProductWithoutDocuments(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            $this->getConnection(['documentsId' => null]),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertSame([], $documents[$id]['documents'] ?? null);
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $productId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product', [
                    new EntityWriteResult($productId, ['productNumber' => 'SW1000'], 'product', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$productId], $indexer->getUpdatedIds($event));
    }

    public function testMappingSupportsTheDocumentsFilter(): void
    {
        $mapping = $this->searchIndexer->mapping([]);

        static::assertSame('nested', $mapping['properties']['documents']['type'] ?? null);
        static::assertContains('documents.id', $this->searchIndexer->getSupportedSearchFields());
    }

    public function testGetUpdatedIdsContainsTheProductsOfAddedDocuments(): void
    {
        $productId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_document', [
                    new EntityWriteResult(Uuid::randomHex(), ['productId' => $productId], 'product_document', EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$productId], $this->searchIndexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsContainsTheProductsOfDeletedDocuments(): void
    {
        $productId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_document', [
                    new EntityWriteResult(Uuid::randomHex(), [], 'product_document', EntityWriteResult::OPERATION_DELETE),
                ], Context::createDefaultContext()),
                new EntityWrittenEvent('product', [
                    new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$productId], $this->searchIndexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsContainsTheVariantsOfProductsWithChangedDocuments(): void
    {
        $productId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([$variantId]);

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_document', [
                    new EntityWriteResult(Uuid::randomHex(), ['productId' => $productId], 'product_document', EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$productId, $variantId], $indexer->getUpdatedIds($event));
    }

    public function testGlobalCriteria(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $search = new Search();
        $result = $indexer->globalCriteria('test', $search);

        $boolQuery = $result->getQueries();
        $boolQueryArray = $boolQuery->toArray();
        $shouldQueries = $boolQueryArray['bool']['should'];

        static::assertCount(4, $shouldQueries);

        $exactIdentifierQueries = [];
        $prefixIdentifierQueries = [];
        $exactIdentifierBoosts = [];
        $simpleQueryStringQuery = null;
        foreach ($shouldQueries as $query) {
            if (isset($query['term'])) {
                $field = array_key_first($query['term']);
                $exactIdentifierQueries[] = $field;
                $exactIdentifierBoosts[] = $query['term'][$field]['boost'];
            } elseif (isset($query['prefix'])) {
                $prefixIdentifierQueries[] = array_key_first($query['prefix']);
            } elseif (isset($query['simple_query_string'])) {
                $simpleQueryStringQuery = $query['simple_query_string'];
            }
        }

        static::assertSame(['ean', 'productNumber', 'manufacturerNumber'], $exactIdentifierQueries);
        static::assertSame([
            SearchRanking::HIGH_SEARCH_RANKING,
            SearchRanking::HIGH_SEARCH_RANKING,
            SearchRanking::HIGH_SEARCH_RANKING,
        ], $exactIdentifierBoosts);
        static::assertSame([], $prefixIdentifierQueries);
        static::assertNotNull($simpleQueryStringQuery, 'SimpleQueryStringQuery for textBoosted should be present');
        static::assertSame(['textBoosted'], $simpleQueryStringQuery['fields']);
        static::assertSame('test*', $simpleQueryStringQuery['query']);
        static::assertSame(SearchRanking::HIGH_SEARCH_RANKING, $simpleQueryStringQuery['boost']);
        static::assertTrue($simpleQueryStringQuery['lenient']);
    }

    public function testGlobalCriteriaDoesNotAddIdentifierPrefixes(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        foreach (['457', '457232'] as $term) {
            $result = $indexer->globalCriteria($term, new Search());

            foreach ($result->getQueries()->toArray()['bool']['should'] as $query) {
                static::assertArrayNotHasKey('prefix', $query);
            }
        }
    }

    public function testGlobalCriteriaDoesNotTreatATermWithATrailingNewlineAsAPrefix(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $result = $indexer->globalCriteria("test\n", new Search());

        $textQueries = array_column($result->getQueries()->toArray()['bool']['should'], 'simple_query_string');

        static::assertSame(["test\n"], array_column($textQueries, 'query'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function getConnection(array $overrides = []): Connection
    {
        $connection = static::createStub(Connection::class);

        $languageId = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
        $connection->method('fetchAllAssociative')->willReturn(
            [
                array_merge([
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'name' => 'Product',
                    'translatedNames' => json_encode([
                        ['languageId' => $languageId, 'name' => 'Product'],
                    ]),
                    'translatedManufacturerNames' => json_encode([
                        ['languageId' => $languageId, 'name' => 'Manufacturer'],
                    ]),
                    'customSearchKeywords' => '[["keywords"]]',
                    'tags' => 'Tag',
                    'tagIds' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
                    'productNumber' => 'SW1000299',
                    'ean' => '4572324423421',
                    'manufacturerNumber' => 'M-100',
                    'active' => 1,
                    'available' => 1,
                    'parentId' => null,
                    'sales' => 10,
                    'stock' => 100,
                    'priceRaw' => json_encode([
                        'cb7d2554b0ce847cd82f3ac9bd1c0dfca' => [
                            'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            'gross' => 100.0,
                            'net' => 84.03,
                            'linked' => true,
                        ],
                    ]),
                    'type' => null,
                    'states' => null,
                    'manufacturerId' => 'aabbccdd11223344556677889900aabb',
                    'categoryIds' => null,
                    'mediaId' => 'aabb11223344556677889900aabbccdd',
                    'documentsId' => '809c1844f4734243b6aa04aba860cd45',
                    'documentIds' => 'c1c2c3c4c5c6c7c8c9c0c1c2c3c4c5c6 d1d2d3d4d5d6d7d8d9d0d1d2d3d4d5d6',
                    'visibilities' => null,
                    'createdAt' => '2024-01-01 00:00:00.000',
                    'updatedAt' => null,
                    'releaseDate' => null,
                ], $overrides),
            ],
        );

        return $connection;
    }
}
