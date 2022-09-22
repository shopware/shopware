<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\LandingPageAdminSearchIndexer;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Admin\Indexer\LandingPageAdminSearchIndexer
 */
class LandingPageAdminSearchIndexerTest extends TestCase
{
    public function testGetEntity(): void
    {
        $indexer = new LandingPageAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepositoryInterface::class)
        );

        static::assertSame(LandingPageDefinition::ENTITY_NAME, $indexer->getEntity());
    }

    public function testGetName(): void
    {
        $indexer = new LandingPageAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepositoryInterface::class)
        );

        static::assertSame('landing-page-listing', $indexer->getName());
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->createMock(EntityRepositoryInterface::class);
        $landingPage = new LandingPageEntity();
        $landingPage->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'landing_page',
                1,
                new EntityCollection([$landingPage]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new LandingPageAdminSearchIndexer(
            $this->createMock(Connection::class),
            $this->createMock(IteratorFactory::class),
            $repository
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

        $indexer = new LandingPageAdminSearchIndexer(
            $connection,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepositoryInterface::class)
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('809c1844f4734243b6aa04aba860cd45 landing page landing page tags', $document['text']);
    }

    private function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);

        $statement = $this->createMock(Statement::class);
        $statement->method('fetchAll')->willReturnOnConsecutiveCalls(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'name' => 'Landing page',
                    'tags' => 'Landing page tags',
                ],
            ],
        );

        $queryBuilder->method('execute')->willReturn($statement);

        $connection
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        return $connection;
    }
}
