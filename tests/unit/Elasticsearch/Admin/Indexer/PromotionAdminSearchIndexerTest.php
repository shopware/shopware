<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
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
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;

/**
 * @internal
 */
#[CoversClass(PromotionAdminSearchIndexer::class)]
class PromotionAdminSearchIndexerTest extends TestCase
{
    private PromotionAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new PromotionAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            100
        );
    }

    public function testGetUpdatedIds(): void
    {
        $indexer = new PromotionAdminSearchIndexer(
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
                new EntityWrittenEvent('promotion_translation', [
                    new EntityWriteResult(['promotionId' => $id], ['name' => 'Promo'], 'promotion_translation', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$id], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(PromotionDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('promotion-listing', $this->searchIndexer->getName());
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
        $promotion = new PromotionEntity();
        $promotion->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'promotion',
                1,
                new EntityCollection([$promotion]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new PromotionAdminSearchIndexer(
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

        $indexer = new PromotionAdminSearchIndexer(
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
        static::assertSame('promotion summer10 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame('SUMMER10', $document['code']);
        static::assertTrue($document['active']);
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
                    'name' => 'Promotion',
                    'translatedNames' => json_encode([
                        ['languageId' => $languageId, 'name' => 'Promotion'],
                    ]),
                    'code' => 'SUMMER10',
                    'active' => 1,
                    'validFrom' => '2024-01-01 00:00:00.000',
                    'validUntil' => '2025-01-01 00:00:00.000',
                    'createdAt' => '2024-01-01 00:00:00.000',
                ],
            ],
        );

        return $connection;
    }
}
