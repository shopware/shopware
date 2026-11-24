<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
        $otherProductId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', [
                new EntityWriteResult($documentId, [
                    'id' => $documentId,
                    'versionId' => Defaults::LIVE_VERSION,
                    'productId' => $productId,
                    'productVersionId' => Defaults::LIVE_VERSION,
                ], 'product_document', EntityWriteResult::OPERATION_INSERT),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('product', [
                new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
                new EntityWriteResult($otherProductId, ['id' => $otherProductId, 'versionId' => Defaults::LIVE_VERSION], 'product', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);

        static::assertSame([$productId], $this->searchIndexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsContainsTheProductsOfDeletedDocuments(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', [
                new EntityWriteResult($documentId, ['id' => $documentId, 'versionId' => Defaults::LIVE_VERSION], 'product_document', EntityWriteResult::OPERATION_DELETE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('product', [
                new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);

        static::assertSame([$productId], $this->searchIndexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsContainsTheVariantsOfProductsWithChangedDocuments(): void
    {
        $productId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$variantId => $productId]);

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', [
                new EntityWriteResult($documentId, [
                    'id' => $documentId,
                    'versionId' => Defaults::LIVE_VERSION,
                    'productId' => $productId,
                    'productVersionId' => Defaults::LIVE_VERSION,
                ], 'product_document', EntityWriteResult::OPERATION_INSERT),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('product', [
                new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);

        static::assertSame([$productId, $variantId], $indexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsLeavesTheVariantsOfLargeFamiliesToTheQueuedInheritanceUpdate(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();
        $variantIds = array_map(static fn (): string => Uuid::randomHex(), range(1, ProductIndexer::UPDATE_IDS_CHUNK_SIZE - 1));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(array_fill_keys($variantIds, $productId));

        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => $registry,
        );

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', [
                new EntityWriteResult($documentId, [
                    'id' => $documentId,
                    'versionId' => Defaults::LIVE_VERSION,
                    'productId' => $productId,
                    'productVersionId' => Defaults::LIVE_VERSION,
                ], 'product_document', EntityWriteResult::OPERATION_INSERT),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('product', [
                new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);

        static::assertSame([$productId], $indexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsCountsVariantsWithOwnDocumentsOnceForTheFamilySize(): void
    {
        $productId = Uuid::randomHex();
        $variantIds = array_map(static fn (): string => Uuid::randomHex(), range(1, 35));
        $variantsWithDocuments = \array_slice($variantIds, 0, 15);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(array_fill_keys($variantIds, $productId));

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        $documentResults = [];
        $productResults = [];
        foreach ([$productId, ...$variantsWithDocuments] as $ownerId) {
            $documentId = Uuid::randomHex();
            $documentResults[] = new EntityWriteResult($documentId, [
                'id' => $documentId,
                'versionId' => Defaults::LIVE_VERSION,
                'productId' => $ownerId,
                'productVersionId' => Defaults::LIVE_VERSION,
            ], 'product_document', EntityWriteResult::OPERATION_INSERT);
            $productResults[] = new EntityWriteResult($ownerId, [], 'product', EntityWriteResult::OPERATION_UPDATE);
        }

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', $documentResults, Context::createDefaultContext()),
            new EntityWrittenEvent('product', $productResults, Context::createDefaultContext()),
        ]);

        $updatedIds = $indexer->getUpdatedIds($event);

        static::assertCount(36, $updatedIds);
        static::assertEqualsCanonicalizing([$productId, ...$variantIds], $updatedIds);
    }

    public function testGetUpdatedIdsIgnoresDocumentUpdates(): void
    {
        $productId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        $event = $this->createWrittenEvent([
            new EntityWrittenEvent('product_document', [
                new EntityWriteResult($documentId, ['id' => $documentId, 'versionId' => Defaults::LIVE_VERSION, 'position' => 2], 'product_document', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('product', [
                new EntityWriteResult($productId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);

        static::assertSame([], $indexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsContainsTheVariantsOfWrittenProducts(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::stringContains('parent_id IN (:ids)'),
                static::callback(static fn (array $params): bool => $params['ids'] === [Uuid::fromHexToBytes($parentId)])
            )
            ->willReturn([$variantId => $parentId]);

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($indexer, $parentId, $variantId): void {
            static::assertSame([$parentId, $variantId], $indexer->getUpdatedIds($this->createProductWrittenEvent($parentId)));
        });
    }

    public function testGetUpdatedIdsLeavesTheVariantsOfLargeWrittenFamiliesToTheQueuedInheritanceUpdate(): void
    {
        $parentId = Uuid::randomHex();
        $variantIds = array_map(static fn (): string => Uuid::randomHex(), range(1, ProductIndexer::UPDATE_IDS_CHUNK_SIZE - 1));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(array_fill_keys($variantIds, $parentId));

        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('updateIds');

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => $registry,
        );

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($indexer, $parentId): void {
            static::assertSame([$parentId], $indexer->getUpdatedIds($this->createProductWrittenEvent($parentId)));
        });
    }

    public function testGetUpdatedIdsQueuesTheVariantsOfLargeFamiliesWhenOnlyAnAssociationIsWritten(): void
    {
        $parentId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $variantIds = array_map(static fn (): string => Uuid::randomHex(), range(1, ProductIndexer::UPDATE_IDS_CHUNK_SIZE));
        $context = Context::createDefaultContext();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(array_fill_keys($variantIds, $parentId));

        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())
            ->method('updateIds')
            ->with(ProductDefinition::ENTITY_NAME, $variantIds, $context);

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => $registry,
        );

        $primaryKey = ['productId' => $parentId, 'productVersionId' => Defaults::LIVE_VERSION, 'tagId' => $tagId];
        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent('product_tag', [
                new EntityWriteResult($primaryKey, $primaryKey, 'product_tag', EntityWriteResult::OPERATION_INSERT),
            ], $context),
        ]), []);

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($indexer, $event, $parentId): void {
            static::assertSame([$parentId], $indexer->getUpdatedIds($event));
        });
    }

    public function testGetUpdatedIdsLoadsTheVariantsOfWrittenProductsAndProductsWithChangedDocumentsInOneQuery(): void
    {
        $writtenProductId = Uuid::randomHex();
        $productWithDocumentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::stringContains('parent_id IN (:ids)'),
                static::callback(static fn (array $params): bool => $params['ids'] === Uuid::fromHexToBytesList([$writtenProductId, $productWithDocumentId]))
            )
            ->willReturn([$variantId => $writtenProductId]);

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_document', [
                    new EntityWriteResult($documentId, [
                        'id' => $documentId,
                        'versionId' => Defaults::LIVE_VERSION,
                        'productId' => $productWithDocumentId,
                        'productVersionId' => Defaults::LIVE_VERSION,
                    ], 'product_document', EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
                new EntityWrittenEvent('product', [
                    new EntityWriteResult($writtenProductId, ['active' => false], 'product', EntityWriteResult::OPERATION_UPDATE),
                    new EntityWriteResult($productWithDocumentId, [], 'product', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        Feature::fake(['ENABLE_OPENSEARCH_FOR_ADMIN_API'], function () use ($indexer, $event, $writtenProductId, $productWithDocumentId, $variantId): void {
            static::assertSame([$writtenProductId, $productWithDocumentId, $variantId], $indexer->getUpdatedIds($event));
        });
    }

    public function testGetUpdatedIdsDoesNotLoadVariantsWithoutTheAdvancedAdminSearch(): void
    {
        $productId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $indexer = new ProductAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        Feature::withFeatureDisabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', function () use ($indexer, $productId): void {
            static::assertSame([$productId], $indexer->getUpdatedIds($this->createProductWrittenEvent($productId)));
        });
    }

    public function testGlobalCriteria(): void
    {
        $indexer = new ProductAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
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
            100,
            static fn (): AdminSearchRegistry => static::createStub(AdminSearchRegistry::class),
        );

        $result = $indexer->globalCriteria("test\n", new Search());

        $textQueries = array_column($result->getQueries()->toArray()['bool']['should'], 'simple_query_string');

        static::assertSame(["test\n"], array_column($textQueries, 'query'));
    }

    private function createProductWrittenEvent(string $productId): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product', [
                    new EntityWriteResult($productId, ['active' => false], 'product', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );
    }

    /**
     * @param list<EntityWrittenEvent<string>> $events
     */
    private function createWrittenEvent(array $events): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection($events), []);
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
