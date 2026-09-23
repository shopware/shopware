<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class ElasticsearchIndexerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    public function testFirstIndexDoesNotCreateTask(): void
    {
        $c = static::getContainer()->get(Connection::class);
        $beforeResult = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertSame([], $beforeResult);

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);
        $indexer->iterate(null);

        $afterResult = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertSame([], $afterResult);
    }

    public function testCreateIndicesCreatesAliasWithoutIndexTask(): void
    {
        $c = static::getContainer()->get(Connection::class);
        $client = static::getContainer()->get(Client::class);
        $helper = static::getContainer()->get(ElasticsearchHelper::class);
        $definition = static::getContainer()->get(ProductDefinition::class);

        $alias = $helper->getIndexName($definition);
        static::assertFalse($client->indices()->existsAlias(['name' => $alias]));

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);
        $indexer->createIndices();

        static::assertTrue($client->indices()->existsAlias(['name' => $alias]));
        static::assertSame([], $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    public function testSecondIndexingCreatesTask(): void
    {
        $c = static::getContainer()->get(Connection::class);
        $before = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertEmpty($before);

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);

        $indexer->iterate(null);
        $indexer->iterate(null);

        $after = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertNotEmpty($after);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }
}
